<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 5:08 PM
 */

namespace Kicken\JSONRPC\Client;


use Amp\Future;
use Kicken\JSONRPC\Exception\JSONRPCException;
use Kicken\JSONRPC\Exception\MalformedJsonException;
use Kicken\JSONRPC\JSONReader;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Kicken\JSONRPC\SuccessfulResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use RuntimeException;
use function Amp\async;

class Client implements LoggerAwareInterface {
    use LoggerAwareTrait;

    /** @var resource */
    private $stream = null;
    private int $idCounter = 0;
    private readonly JSONReader $reader;
    private array $responseMap;

    public function __construct(
        private readonly string $url,
        private readonly int $timeout = 10,
        ?LoggerInterface $logger = null
    ){
        $this->logger = $logger ?? new NullLogger();
        $this->reader = new JSONReader();
        $this->responseMap = [];
    }

    private function bufferResponses() : void{
        foreach ($this->reader->readObjects() as $batch){
            if (!is_array($batch)){
                $batch = [$batch];
            }

            foreach ($batch as $document){
                try {
                    $response = SuccessfulResponse::createFromJsonObject($document);
                    $this->responseMap[$response->id] = $response;
                } catch (JSONRPCException $ex){
                    $this->logger->warning('Unable to process response document', [
                        'document' => $document,
                        'exception' => $ex
                    ]);
                }
            }
        }
    }

    private function connect() : Future{
        if ($this->stream){
            return Future::complete($this->stream);
        }

        return async(function(){
            $this->stream = stream_socket_client($this->url, $errorCode, $errorString, $this->timeout, STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT);
            if (!$this->stream){
                throw new RuntimeException('Could not connect to server');
            }
            $suspension = EventLoop::getSuspension();
            EventLoop::onWritable($this->stream, function(string $callbackId) use ($suspension){
                EventLoop::cancel($callbackId);
                $suspension->resume();
            });
            $suspension->suspend();

            if (!stream_socket_get_name($this->stream, true)){
                throw new RuntimeException('Unable to connect to server.');
            }

            return $this->stream;
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
        $json = json_encode($request);
        if (json_last_error() !== JSON_ERROR_NONE){
            throw new MalformedJsonException();
        }

        $this->connect()->await();

        $this->writeToSocket($json);

        $response = null;
        if (!$request->isNotification()){
            $response = $this->readResponse($request);
        }

        return $response;
    }

    private function createRequest(string $method, array|object|null $params, bool $notification) : Request{
        $id = $notification ? null : ++$this->idCounter;

        return new Request($method, $params, $id, $notification);
    }

    private function readResponse(Request $request) : Response{
        $suspension = EventLoop::getSuspension();
        $id = $request->getId();
        $callbackId = EventLoop::onReadable($this->stream, function() use ($suspension){
            $data = fread($this->stream, 8192);
            if (!is_string($data) || $data === ''){
                throw new RuntimeException('Client disconnected');
            }

            $this->reader->feed($data);
            $suspension->resume();
        });

        while (!isset($this->responseMap[$id])){
            $suspension->suspend();
            $this->bufferResponses();
        }
        EventLoop::cancel($callbackId);

        return $this->responseMap[$id];
    }

    private function writeToSocket(string $buffer) : void{
        $suspension = EventLoop::getSuspension();
        $callbackId = EventLoop::onWritable($this->stream, function() use (&$buffer, $suspension){
            $length = strlen($buffer);
            $written = fwrite($this->stream, $buffer, $length);
            if (!is_int($written)){
                $this->logger->notice('Write error, disconnecting.');
            }

            if ($written === $length){
                $this->logger->debug('Flushed write buffer.');
                $buffer = '';
            } else if ($written > 0){
                $buffer = substr($buffer, $written);
                $this->logger->debug('Partially flushed write buffer', [
                    'bytesFlushed' => $written,
                    'remainingBuffer' => $buffer
                ]);
            }
            $suspension->resume();
        });

        while ($buffer !== ''){
            $suspension->suspend();
        }
        EventLoop::cancel($callbackId);
    }
}
