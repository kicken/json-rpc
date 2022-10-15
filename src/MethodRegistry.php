<?php

namespace Kicken\JSONRPC;

use Kicken\JSONRPC\Exception\MethodAlreadyRegisteredException;
use Kicken\JSONRPC\Exception\MethodNotFoundException;

class MethodRegistry {
    private $methodList = [];

    public function register($methodName, $handler){
        if (array_key_exists($methodName, $this->methodList)){
            throw new MethodAlreadyRegisteredException($methodName);
        }

        $this->methodList[$methodName] = $handler;
    }

    public function unregister($methodName){
        unset($this->methodList[$methodName]);
    }

    public function execute(Request $request){
        $method = $request->getMethod();
        $callback = isset($this->methodList[$method]) ? $this->methodList[$method] : null;
        if (!is_callable($callback)){
            throw new MethodNotFoundException($method);
        }

        return call_user_func($callback, $request);
    }
}