<?php

namespace Kicken\JSONRPC;

use Kicken\JSONRPC\Exception\MethodAlreadyRegisteredException;
use Kicken\JSONRPC\Exception\MethodNotFoundException;
use Kicken\JSONRPC\Server\ClientSession;
use TypeError;

class MethodRegistry {
    private array $methodList = [];

    public function __construct(
        array $methodList = [],
    ){
        array_walk($methodList, function($method){
            if (!$method instanceof RPCMethod){
                throw new TypeError('Methods must be instances of RPCMethod');
            }

            $this->register($method);
        });
    }

    public function isRegistered(string $methodName) : bool{
        return isset($this->methodList[$methodName]);
    }

    public function register(RPCMethod $method) : void{
        $methodName = $method->getName();
        if (array_key_exists($methodName, $this->methodList)){
            throw new MethodAlreadyRegisteredException($methodName);
        }

        $this->methodList[$methodName] = $method;
    }

    public function unregister(string $methodName) : void{
        unset($this->methodList[$methodName]);
    }

    public function execute(Request $request, ClientSession $session) : mixed{
        $method = $request->getMethod();
        /** @var RPCMethod $handler */
        $handler = $this->methodList[$method] ?? null;
        if (!$handler){
            throw new MethodNotFoundException($method);
        }

        return $handler->run($request, $session);
    }
}
