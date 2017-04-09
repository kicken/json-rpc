<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 5:46 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\MethodAlreadyRegisteredException;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

class Server {
    /** @var LoopInterface  */
    protected $loop = null;
    /** @var ServerInterface  */
    protected $stream = null;
    /** @var array */
    protected $methods = [];

    public function __construct($url, LoopInterface $loop){
        $this->loop = $loop;
        $this->createServerStream($url);
    }

    public function registerMethod($method, callable $callback){
        if (array_key_exists($method, $this->methods)){
            throw new MethodAlreadyRegisteredException($method);
        }

        $this->methods[$method] = $callback;
    }

    protected function createServerStream($url){
        $this->stream = new \React\Socket\Server($url, $this->loop);
        $this->stream->on('connection', function(ConnectionInterface $connection){
            $this->handleConnection($connection);
        });
    }

    protected function handleConnection(ConnectionInterface $connection){
        new Connection($connection, $this->methods);
    }
}
