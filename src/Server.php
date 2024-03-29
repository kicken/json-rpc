<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 5:46 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\MalformedJsonException;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

class Server {
    /** @var LoopInterface */
    protected $loop = null;
    /** @var ServerInterface */
    protected $stream = null;
    /** @var MethodRegistry */
    protected $methodRegistry;

    public function __construct($url, MethodRegistry $registry, LoopInterface $loop){
        $this->loop = $loop;
        $this->methodRegistry = $registry;
        $this->createServerStream($url);
    }

    private function createServerStream($url){
        $this->stream = new \React\Socket\Server($url, $this->loop);
        $this->stream->on('connection', function(ConnectionInterface $connection){
            $this->handleConnection($connection);
        });
    }

    private function handleConnection(ConnectionInterface $connection){
        $reader = new JSONReader($connection);
        $reader->on('data', function($document) use ($connection){
            if (is_array($document)){
                $response = $this->processBatch($document);
            } else {
                $response = $this->processSingle($document);
            }

            if ($response){
                $this->processResponse($connection, $response);
            }
        });
        $reader->on('error', function() use ($connection){
            $connection->close();
        });
    }

    private function processBatch($document){
        $responseList = [];
        foreach ($document as $data){
            $result = $this->processSingle($data);
            if ($result){
                $responseList[] = $result;
            }
        }

        return $responseList;
    }

    private function processSingle($document){
        try {
            $request = Request::createFromJsonObject($document);
        } catch (\Exception $ex){
            return ErrorResponse::createFromException(null, $ex);
        }

        try {
            $result = $this->methodRegistry->execute($request);
            $response = new Response($request->getId(), $result);
        } catch (\Exception $ex){
            $response = ErrorResponse::createFromException($request->getId(), $ex);
        }

        if ($request->isNotification()){
            $response = null;
        }

        return $response;
    }

    /**
     * @param ConnectionInterface $connection
     * @param Response|Response[] $response
     */
    private function processResponse(ConnectionInterface $connection, $response){
        if (is_array($response)){
            $jsonList = [];
            foreach ($response as $item){
                $jsonList[] = $this->encodeResponse($item);
            }

            $json = '[' . implode(',', $jsonList) . ']';
        } else {
            $json = $this->encodeResponse($response);
        }

        $connection->write($json);
    }

    private function encodeResponse(Response $item){
        $json = json_encode($item);
        if (json_last_error() !== JSON_ERROR_NONE){
            $error = ErrorResponse::createFromException($item->getId(), new MalformedJsonException());
            $json = json_encode($error);
        }

        return $json;
    }
}
