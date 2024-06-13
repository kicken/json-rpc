<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 5:08 PM
 */

namespace Kicken\JSONRPC\Client;


use Kicken\JSONRPC\Exception\UnableToConnectException;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;

class Client implements LoggerAwareInterface {
    use LoggerAwareTrait;

    private int $idCounter = 0;
    private ?ClientConnection $connection = null;

    public function __construct(
        private readonly string $ip,
        private readonly int $port = 6850,
        private readonly int $timeout = 10,
        ?LoggerInterface $logger = null
    ){
        $this->logger = $logger ?? new NullLogger();
    }

    private function connect() : void{
        if ($this->connection?->isConnected()){
            return;
        }

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
                    $suspension->throw(new UnableToConnectException($url, ETIMEDOUT, 'Time out while trying to connect.'));
                });

                try {
                    $suspension->suspend();
                    $remote = stream_socket_get_name($stream, true);
                    if (!$remote){
                        throw new UnableToConnectException($url, ETIMEDOUT, 'Time out while trying to connect.');
                    }

                    $this->logger->debug('Successfully connected', [
                        'stream' => get_resource_id($stream)
                    ]);
                    $connection = new ClientConnection($stream, $this->logger);
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

        $this->connection = $connection;
    }

    public function sendRequest(string $method, array|object|null $params = null) : Response{
        $request = $this->createRequest($method, $params, false);

        return $this->send($request);
    }

    public function sendNotification(string $method, array|object|null $params = null) : void{
        $request = $this->createRequest($method, $params, true);

        $this->send($request);
    }

    private function send(Request $request) : ?Response{
        $this->connect();

        return $this->connection->processRequest($request);
    }

    private function createRequest(string $method, array|object|null $params, bool $notification) : Request{
        $id = $notification ? null : ++$this->idCounter;

        return new Request($method, $params, $id, $notification);
    }
}
