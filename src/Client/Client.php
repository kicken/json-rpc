<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 5:08 PM
 */

namespace Kicken\JSONRPC\Client;


use Amp\Future;
use Kicken\JSONRPC\Exception\UnableToConnectException;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use function Amp\async;

class Client implements LoggerAwareInterface {
    use LoggerAwareTrait;

    private int $idCounter = 0;

    public function __construct(
        private readonly string $ip,
        private readonly int $port = 6850,
        private readonly int $timeout = 10,
        ?LoggerInterface $logger = null
    ){
        $this->logger = $logger ?? new NullLogger();
    }

    private function connect() : ClientConnection{
        $connection = null;
        $suspension = EventLoop::getSuspension();
        $attempt = 0;
        $url = sprintf('tcp://%s:%d', $this->ip, $this->port);
        do {
            $attempt++;
            try {
                $stream = stream_socket_client($url, $errorCode, $errorString, null, STREAM_CLIENT_ASYNC_CONNECT);
                if (!$stream){
                    throw new UnableToConnectException($url, $errorCode, $errorString);
                }

                $writableCallbackId = EventLoop::onWritable($stream, function() use ($suspension){
                    $suspension->resume(true);
                });
                $timeoutCallbackId = EventLoop::delay($this->timeout, function() use ($suspension, $url){
                    $suspension->throw(new UnableToConnectException($url, 0, 'Time out while trying to connect'));
                });

                try {
                    $suspension->suspend();
                    $remote = stream_socket_get_name($stream, true);
                    if ($remote){
                        $this->logger->debug('Successfully connected', [
                            'stream' => get_resource_id($stream)
                        ]);
                        $connection = new ClientConnection($stream, $this->logger);
                    }
                } finally {
                    EventLoop::cancel($writableCallbackId);
                    EventLoop::cancel($timeoutCallbackId);
                }
            } catch (UnableToConnectException $ex){
                if ($attempt === 5){
                    throw $ex;
                }

                $this->logger->warning('Failed to connect to server, retrying', [
                    'attempt' => $attempt,
                    'url' => $url,
                    'errorCode' => $errorCode,
                    'errorMessage' => $errorString
                ]);
            }
        } while (!$connection);

        return $connection;
    }

    public function sendRequest(string $method, array|object|null $params = null) : Future{
        $request = $this->createRequest($method, $params, false);

        return async($this->send(...), $request);
    }

    public function sendNotification(string $method, array|object|null $params = null) : void{
        $request = $this->createRequest($method, $params, true);
        async($this->send(...), $request);
    }

    private function send(Request $request) : ?Response{
        $client = $this->connect();

        return $client->processRequest($request);
    }

    private function createRequest(string $method, array|object|null $params, bool $notification) : Request{
        $id = $notification ? null : ++$this->idCounter;

        return new Request($method, $params, $id, $notification);
    }

}
