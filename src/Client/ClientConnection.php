<?php

namespace Kicken\JSONRPC\Client;

use Kicken\JSONRPC\ErrorResponse;
use Kicken\JSONRPC\Exception\InvalidJsonException;
use Kicken\JSONRPC\Exception\JSONRPCException;
use Kicken\JSONRPC\Exception\MalformedJsonException;
use Kicken\JSONRPC\JSONReader;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Kicken\JSONRPC\SuccessfulResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use RuntimeException;

class ClientConnection {
    private const CHUNK_SIZE = 1024 * 1024 * 10;

    private readonly LoggerInterface $logger;
    private readonly JSONReader $reader;
    private array $responseMap = [];

    public function __construct(
        private $stream,
        ?LoggerInterface $logger = null
    ){
        $this->logger = $logger ?? new NullLogger();
        $this->reader = new JSONReader();
        stream_set_read_buffer($this->stream, 0);
        stream_set_write_buffer($this->stream, 0);
    }

    public function processRequest(Request $request) : ?Response{
        $requestJson = json_encode($request);
        $error = json_last_error();
        if ($error !== JSON_ERROR_NONE){
            throw new MalformedJsonException($error);
        }

        try {
            $this->writeRequest($requestJson);

            $response = null;
            if (!$request->isNotification()){
                $response = $this->readResponse($request);
            }

            return $response;
        } catch (RuntimeException $ex){
            $this->logger->error('Unable to process request', [
                'exception' => $ex->getMessage()
            ]);

            return ErrorResponse::createFromException($ex);
        }
    }


    private function writeRequest(string $buffer) : void{
        $suspension = EventLoop::getSuspension();
        $callbackId = EventLoop::onWritable($this->stream, function($callbackId, $stream) use (&$buffer, $suspension){
            $length = strlen($buffer);
            $written = fwrite($stream, $buffer, $length);
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

    private function readResponse(Request $request) : Response{
        $suspension = EventLoop::getSuspension();
        $id = $request->getId();
        $callbackId = EventLoop::onReadable($this->stream, function($callbackId, $stream) use ($suspension){
            $data = fread($stream, self::CHUNK_SIZE);
            if (!is_string($data) || $data === ''){
                $suspension->throw(new RuntimeException('Client disconnected'));

                return;
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

    private function bufferResponses() : void{
        foreach ($this->reader->readObjects() as $batch){
            if (!is_array($batch)){
                $batch = [$batch];
            }

            foreach ($batch as $document){
                try {
                    if (property_exists($document, 'error')){
                        $response = ErrorResponse::createFromJsonObject($document);
                    } else if (property_exists($document, 'result')){
                        $response = SuccessfulResponse::createFromJsonObject($document);
                    } else {
                        $response = ErrorResponse::createFromException(new InvalidJsonException('Invalid JSON response.'));
                    }
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
}
