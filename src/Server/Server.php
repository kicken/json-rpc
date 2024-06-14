<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 5:46 PM
 */

namespace Kicken\JSONRPC\Server;

use Exception;
use Kicken\JSONRPC\ErrorResponse;
use Kicken\JSONRPC\MethodRegistry;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Kicken\JSONRPC\SuccessfulResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use RuntimeException;

class Server implements LoggerAwareInterface {
    use LoggerAwareTrait;

    protected readonly MethodRegistry $methodRegistry;
    /** @var array<resource> */
    private array $serverStreamList = [];
    /** @var array<string> */
    private array $serverCallbackIdList = [];
    /** @var array<ClientConnection> */
    private array $clientConnectionList = [];

    public function __construct(MethodRegistry $registry, LoggerInterface $logger = null){
        $this->logger = $logger ?? new NullLogger();
        $this->methodRegistry = $registry;
    }

    public function start(int $port, string $ip = '0.0.0.0', string $certificateFile = null, string $privateKeyFile = null, bool $block = true) : void{
        if ($certificateFile && !$privateKeyFile){
            $this->logger->error('Private key is required when providing a certificate.', [
                'certificate' => $certificateFile,
                'privateKey' => $privateKeyFile
            ]);
            throw new RuntimeException('Private key is required when providing a certificate.');
        }

        if ($certificateFile && !is_readable($certificateFile)){
            $this->logger->error('Unable to read certificate file.', [
                'certificate' => $certificateFile,
                'privateKey' => $privateKeyFile
            ]);
            throw new RuntimeException('Unable to read certificate file.');
        }
        if ($privateKeyFile && !is_readable($privateKeyFile)){
            $this->logger->error('Unable to read private key file.', [
                'certificate' => $certificateFile,
                'privateKey' => $privateKeyFile
            ]);
            throw new RuntimeException('Unable to read private key file.');
        }

        $context = stream_context_create([
            'socket' => [
                'so_reuseport' => true
            ]
        ]);
        if ($certificateFile){
            stream_context_set_option($context, [
                'ssl' => [
                    'local_cert' => $certificateFile,
                    'local_pk' => $privateKeyFile,
                ]
            ]);
        }

        $url = sprintf('tcp://%s:%d', $ip, $port);
        $stream = stream_socket_server($url, $errorCode, $errorMessage, context: $context);
        if (!$stream){
            $this->logger->error('Unable to create server socket', [
                'code' => $errorCode,
                'message' => $errorMessage,
                'url' => $url,
                'certificateFile' => $certificateFile,
                'privateKeyFile' => $privateKeyFile,
            ]);
            throw new RuntimeException('Unable to create socket');
        }


        $this->acceptClientConnections($stream, $certificateFile !== null);
        if ($block){
            EventLoop::run();
        }
    }

    public function shutdown() : void{
        foreach ($this->serverCallbackIdList as $callbackId){
            EventLoop::cancel($callbackId);
        }
        foreach ($this->serverStreamList as $stream){
            fclose($stream);
        }
        foreach ($this->clientConnectionList as $client){
            $client->disconnect();
        }
    }

    private function acceptClientConnections($serverStream, bool $enableCrypto) : void{
        $this->serverStreamList[] = $serverStream;
        $this->serverCallbackIdList[] = EventLoop::onReadable($serverStream, function() use ($serverStream, $enableCrypto){
            $clientStream = stream_socket_accept($serverStream);
            if ($clientStream){
                $clientIp = stream_socket_get_name($clientStream, true);
                if ($enableCrypto && !$this->enableCrypto($clientStream)){
                    $this->logger->warning('Could not enable crypto for client.', [
                        'client' => $clientIp
                    ]);
                    fwrite($clientStream, json_encode(new ErrorResponse(ErrorResponse::GENERIC_ERROR_CODE, 'Connection requires TLS')));
                    fclose($clientStream);

                    return;
                }

                stream_set_blocking($clientStream, false);
                $this->logger->info('Successfully established client connection.', [
                    'client' => $clientIp
                ]);
                $this->handleClient($clientStream);
            }
        });
    }

    private function handleClient($clientStream) : void{
        $client = new ClientConnection($clientStream, $this->logger);
        $this->clientConnectionList[] = $client;

        foreach ($client->readMessages() as $message){
            if (is_array($message)){
                $response = $this->processBatch($message);
            } else {
                $response = $this->processSingle($message);
            }

            if ($response){
                $client->writeResponse($response);
            }
        }
    }

    private function enableCrypto($stream) : bool{
        set_error_handler(function(int $errNo, string $errString) use ($stream){
            return $errNo === E_WARNING && str_contains($errString, 'stream_socket_enable_crypto');
        });
        try {
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_SERVER)){
                return false;
            }

            return true;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param array<Request> $document
     *
     * @return array<Response>
     */
    private function processBatch(array $document) : array{
        $responseList = [];
        foreach ($document as $data){
            $result = $this->processSingle($data);
            if ($result){
                $responseList[] = $result;
            }
        }

        return $responseList;
    }

    private function processSingle(Request|Response $request) : Response|null{
        if ($request instanceof Response){
            return $request;
        }

        try {
            $result = $this->methodRegistry->execute($request);

            $response = null;
            if (!$request->isNotification()){
                $response = new SuccessfulResponse($request->getId(), $result);
            }
        } catch (Exception $ex){
            $response = ErrorResponse::createFromException($ex, $request->getId());
        }

        return $response;
    }
}
