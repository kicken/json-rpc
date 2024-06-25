<?php

namespace Kicken\JSONRPC\Server;

use Generator;
use Kicken\JSONRPC\ErrorResponse;
use Kicken\JSONRPC\Exception\InvalidJsonException;
use Kicken\JSONRPC\Exception\MalformedJsonException;
use Kicken\JSONRPC\JSONReader;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use stdClass;

class ClientConnection {
    private const CHUNK_SIZE = 1024 * 1024 * 10;

    public readonly string $clientIp;

    private readonly string $readableCallbackId;
    private readonly string $writableCallbackId;
    private string $writeBuffer = '';
    private EventLoop\Suspension $suspension;
    private JSONReader $reader;
    private ClientSession $session;

    public function __construct(private $stream, private readonly LoggerInterface $logger){
        $this->suspension = EventLoop::getSuspension();
        $this->clientIp = stream_socket_get_name($this->stream, true);
        $this->readableCallbackId = EventLoop::onReadable($this->stream, $this->streamReadable(...));
        $this->writableCallbackId = EventLoop::onWritable($this->stream, $this->streamWritable(...));
        $this->reader = new JSONReader();
        $this->session = new ClientSession($this->clientIp);
        EventLoop::disable($this->writableCallbackId);

        stream_set_read_buffer($this->stream, 0);
        stream_set_write_buffer($this->stream, 0);
    }

    public function getSession() : ClientSession{
        return $this->session;
    }

    public function disconnect() : void{
        if (!$this->isDisconnected()){
            EventLoop::cancel($this->readableCallbackId);
            EventLoop::cancel($this->writableCallbackId);
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * @return Generator<Request|array>
     */
    public function readMessages() : Generator{
        while ($this->stream){
            try {
                $this->logger->debug(sprintf('[%s] Reading messages', $this->clientIp));
                foreach ($this->reader->readObjects() as $object){
                    if (is_array($object)){
                        yield $this->convertBatchToRequests($object);
                    } else {
                        yield $this->convertObjectToRequest($object);
                    }
                }
            } catch (MalformedJsonException $ex){
                $this->writeResponse(ErrorResponse::createFromException($ex));
                $this->reader->reset();
            } finally {
                $this->logger->debug(sprintf('[%s] Completed reading messages', $this->clientIp));
                $this->suspension->suspend();
            }
        }
    }

    public function writeResponse(array|Response $response) : void{
        if ($this->isDisconnected()){
            return;
        }

        if (is_array($response)){
            $json = array_map($this->encodeResponse(...), $response);
            $json = '[' . implode(',', $json) . ']';
        } else {
            $json = $this->encodeResponse($response);
        }

        $this->writeBuffer .= $json;
        EventLoop::enable($this->writableCallbackId);
    }

    private function isDisconnected() : bool{
        return $this->stream === null;
    }

    private function streamReadable() : void{
        try {
            if ($this->isDisconnected()){
                return;
            }

            $totalRead = 0;
            do {
                $data = fread($this->stream, self::CHUNK_SIZE) ?: '';
                if ($data !== ''){
                    $totalRead += $length = strlen($data);
                    $this->reader->feed($data);
                    $this->logger->debug(sprintf('[%s] Buffered incoming data bytes', $this->clientIp), [
                        'count' => $length
                    ]);
                }
            } while ($data !== '');

            if ($totalRead === 0 && feof($this->stream)){
                $this->logger->notice('Connection lost.', [
                    'remoteIp' => stream_socket_get_name($this->stream, true),
                ]);
                $this->disconnect();

                return;
            }
        } finally {
            $this->suspension->resume();
        }
    }

    private function streamWritable() : void{
        $length = strlen($this->writeBuffer);
        $written = fwrite($this->stream, $this->writeBuffer, $length);
        if (!is_int($written)){
            $this->logger->notice(sprintf('[%s] Write error, disconnecting.', $this->clientIp));
            $this->disconnect();

            return;
        }

        if ($written === $length){
            $this->logger->debug(sprintf('[%s] Flushed write buffer.', $this->clientIp));
            $this->writeBuffer = '';
            EventLoop::disable($this->writableCallbackId);
        } else if ($written > 0){
            $this->writeBuffer = substr($this->writeBuffer, $written);
            $this->logger->debug(sprintf('[%s] Partially flushed write buffer', $this->clientIp), [
                'bytesFlushed' => $written,
                'remainingBuffer' => $this->writeBuffer
            ]);
            EventLoop::enable($this->writableCallbackId);
        }
        $this->suspension->resume();
    }

    private function encodeResponse(Response $item) : string{
        $json = json_encode($item);
        if (json_last_error() !== JSON_ERROR_NONE){
            $error = ErrorResponse::createFromException(new MalformedJsonException(json_last_error()), $item->getId());
            $json = json_encode($error);
        }

        return $json;
    }

    private function convertBatchToRequests(stdClass|array $object) : array|Request{
        if (is_array($object)){
            return array_map($this->convertObjectToRequest(...), $object);
        } else {
            return $this->convertObjectToRequest($object);
        }
    }

    private function convertObjectToRequest(stdClass $object) : Request|ErrorResponse{
        try {
            return Request::createFromJsonObject($object);
        } catch (InvalidJsonException $ex){
            return ErrorResponse::createFromException($ex);
        }
    }
}
