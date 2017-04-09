<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:47 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\InvalidRequestException;

class Request {
    /** @var bool */
    private $isNotification;
    /** @var mixed */
    private $id;
    /** @var string */
    private $method;
    /** @var array|\stdClass */
    private $params;

    public function __construct(\stdClass $decodedJson){
        $this->validate($decodedJson);
        $this->isNotification = !property_exists($decodedJson, 'id');
        $this->id = $this->isNotification?null:$decodedJson->id;
        $this->method = $decodedJson->method;
        $this->params = property_exists($decodedJson, 'params')?$decodedJson->params:[];
    }

    private function validate(\stdClass $decodedJson){
        if (!property_exists($decodedJson, 'jsonrpc')){
            throw new InvalidRequestException('Missing required property "jsonrpc"');
        }

        if (!property_exists($decodedJson, 'method')){
            throw new InvalidRequestException('Missing required property "method"');
        }

        if ($decodedJson->jsonrpc !== '2.0'){
            throw new InvalidRequestException('Property "jsonrpc" must be set to "2.0"');
        }

        if (!is_string($decodedJson->method)){
            throw new InvalidRequestException('Property "method" must be a string containing a method name.');
        }

        if (property_exists($decodedJson, 'params')){
            if (!is_array($decodedJson->params) && !is_object($decodedJson->params)){
                throw new InvalidRequestException('Property "params" must be an array or object');
            }
        }
    }

    /**
     * @return bool
     */
    public function isNotification(){
        return $this->isNotification;
    }

    /**
     * @return mixed
     */
    public function getId(){
        return $this->id;
    }

    /**
     * @return string
     */
    public function getMethod(){
        return $this->method;
    }

    /**
     * @return array|\stdClass
     */
    public function getParams(){
        return $this->params;
    }
}