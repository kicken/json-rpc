<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 5:08 PM
 */

namespace Kicken\JSONRPC;


use Evenement\EventEmitterTrait;
use Kicken\JSONRPC\Exception\MalformedJsonException;
use Kicken\JSONRPC\Exception\NotConnectedException;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\SocketClient\ConnectionInterface;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use React\SocketClient\TimeoutConnector;

class Client {
    use EventEmitterTrait;

    /** @var string */
    private $url = '';
    /** @var LoopInterface */
    private $loop = null;
    /** @var ConnectionInterface */
    private $stream = null;
    /** @var int */
    private $idCounter = 0;
    /** @var Deferred[] */
    private $deferredMap;

    public function __construct($url, LoopInterface $loop){
        $this->loop = $loop;
        $this->url = $url;
    }

    public function connect($timeout = -1){
        /** @var ConnectorInterface $connector */
        $connector = new Connector($this->loop);
        if ($timeout === -1){
            $timeout = ini_get('default_socket_timeout');
        }

        if ($timeout > 0){
            $connector = new TimeoutConnector($connector, $timeout, $this->loop);
        }

        /** @var PromiseInterface $promise */
        $promise = $connector->connect($this->url);
        $promise->then(function(ConnectionInterface $stream){
            $this->stream = $stream;
            $jsonStream = new JSONReader($stream);
            $jsonStream->on('data', function($data){
                $response = Response::createFromJsonObject($data);
                $this->processResponse($response);
            });
        });

        return $promise;
    }

    public function sendRequest($method, $params){
        return $this->send($this->createRequest($method, $params, false));
    }

    public function sendNotification($method, $params){
        return $this->send($this->createRequest($method, $params, true));
    }

    private function send(Request $request){
        $this->checkConnection();

        $json = json_encode($request);
        if (json_last_error() !== JSON_ERROR_NONE){
            throw new MalformedJsonException();
        }

        $deferred = new Deferred();
        if ($request->isNotification()){
            $deferred->resolve(null);
        } else {
            $this->deferredMap[$request->getId()] = $deferred;
        }

        $this->stream->write($json);

        return $deferred->promise();
    }

    private function checkConnection(){
        if (!$this->stream || !$this->stream->isWritable()){
            throw new NotConnectedException();
        }
    }

    private function createRequest($method, $params, $notification){
        $id = ++$this->idCounter;

        return new Request($method, $params, $id, $notification);
    }

    private function processResponse(Response $response){
        $id = $response->getId();
        if (isset($this->deferredMap[$id])){
            $deferred = $this->deferredMap[$id];
            unset($this->deferredMap[$id]);

            $deferred->resolve($response);
        }
    }
}
