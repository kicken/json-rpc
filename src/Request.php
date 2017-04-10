<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:47 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\InvalidJsonException;

class Request implements \JsonSerializable {
    /** @var bool */
    private $isNotification;
    /** @var mixed */
    private $id;
    /** @var string */
    private $method;
    /** @var mixed */
    private $params;

    public function __construct($method, $params = null, $id = null, $isNotification = false){
        $this->isNotification = $isNotification;
        $this->id = $id;
        $this->method = $method;
        $this->params = $params;

        if ($params !== null){
            if (!is_array($params) && !is_object($params)){
                throw new \InvalidArgumentException('Params must be an array or object');
            }
        }
    }

    public static function createFromJsonObject($data){
        if (!is_object($data)){
            throw new InvalidJsonException('Json structure is not an object.');
        }

        if (!property_exists($data, 'jsonrpc')){
            throw new InvalidJsonException('Missing required property "jsonrpc"');
        }

        if (!property_exists($data, 'method')){
            throw new InvalidJsonException('Missing required property "method"');
        }

        if ($data->jsonrpc !== '2.0'){
            throw new InvalidJsonException('Property "jsonrpc" must be set to "2.0"');
        }

        if (!is_string($data->method)){
            throw new InvalidJsonException('Property "method" must be a string containing a method name.');
        }

        $params = null;
        if (property_exists($data, 'params')){
            if (!is_array($data->params) && !is_object($data->params)){
                throw new InvalidJsonException('Property "params" must be an array or object');
            } else {
                $params = $data->params;
            }
        }

        $isNotification = true;
        $id = null;
        if (property_exists($data, 'id')){
            $isNotification = false;
            $id = $data->id;
        }

        return new self($data->method, $params, $id, $isNotification);
    }

    function jsonSerialize(){
        $data = [
            'jsonrpc' => '2.0'
            , 'method' => $this->method
        ];

        if ($this->params !== null){
            $data['params'] = $this->params;
        }

        if (!$this->isNotification){
            $data['id'] = $this->id;
        }

        return $data;
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
