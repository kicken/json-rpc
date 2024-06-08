<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 5:08 PM
 */

namespace Kicken\JSONRPC\Client;


use Amp\Future;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use RuntimeException;
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


    private function connect() : Future{
        return async(function(){
            $url = sprintf('tcp://%s:%d', $this->ip, $this->port);
            $stream = stream_socket_client($url, $errorCode, $errorString, null, STREAM_CLIENT_ASYNC_CONNECT);
            if (!$stream){
                $this->logger->error('Failed to connect to server', [
                    'url' => $url,
                    'errorCode' => $errorCode,
                    'errorMessage' => $errorString,
                ]);
                throw new RuntimeException('Could not connect to server');
            }

            $suspension = EventLoop::getSuspension();
            $writableCallbackId = EventLoop::onWritable($stream, function() use ($suspension){
                $suspension->resume();
            });
            $timeoutCallbackId = EventLoop::delay($this->timeout, function() use ($suspension, $url){
                $this->logger->error('Timeout while attempting to connect to server', [
                    'url' => $url
                ]);
                $suspension->throw(new RuntimeException('Unable to connect to server, timeout reached.'));
            });
            $suspension->suspend();
            EventLoop::cancel($writableCallbackId);
            EventLoop::cancel($timeoutCallbackId);

            $remote = stream_socket_get_name($stream, true);
            if (!$remote){
                $this->logger->error('Unable to connect to socket.', [
                    'url' => $url,
                ]);
                throw new RuntimeException('Could not connect to server');
            }

            return new ClientConnection($stream, $this->logger);
        });
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
        /** @var ClientConnection $client */
        $client = $this->connect()->await();

        return $client->processRequest($request);
    }

    private function createRequest(string $method, array|object|null $params, bool $notification) : Request{
        $id = $notification ? null : ++$this->idCounter;

        return new Request($method, $params, $id, $notification);
    }

}
