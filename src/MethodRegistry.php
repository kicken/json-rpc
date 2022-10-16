<?php

namespace Kicken\JSONRPC;

use Kicken\JSONRPC\Exception\MethodAlreadyRegisteredException;
use Kicken\JSONRPC\Exception\MethodNotFoundException;

class MethodRegistry {
    private $methodList = [];

    public function register(RPCMethod $method){
        $methodName = $method->getName();
        if (array_key_exists($methodName, $this->methodList)){
            throw new MethodAlreadyRegisteredException($methodName);
        }

        $this->methodList[$methodName] = $method;
    }

    public function unregister($methodName){
        unset($this->methodList[$methodName]);
    }

    public function execute(Request $request){
        $method = $request->getMethod();
        /** @var RPCMethod $handler */
        $handler = isset($this->methodList[$method]) ? $this->methodList[$method] : null;
        if (!$handler){
            throw new MethodNotFoundException($method);
        }

        return $handler->run($request);
    }
}
