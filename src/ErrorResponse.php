<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 7:11 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\InvalidJsonException;
use Kicken\JSONRPC\Exception\JSONRPCException;

class ErrorResponse extends Response {
    private $id;
    private $code;
    private $message;
    private $data;

    public function __construct($id, $code, $message, $data){
        parent::__construct($id, false);
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function jsonSerialize(){
        $result = parent::jsonSerialize();
        unset($result['result']);

        $result['error'] = [
            'code' => $this->code
            , 'message' => $this->message
        ];

        if ($this->data){
            $result['error']['data'] = $this->data;
        }

        return $result;
    }

    public static function createFromJsonObject($data){
        if (!property_exists($data, 'jsonrpc')){
            throw new InvalidJsonException('Missing required property "jsonrpc"');
        }

        if (!property_exists($data, 'error')){
            throw new InvalidJsonException('Missing required property "error"');
        }

        if ($data->jsonrpc !== '2.0'){
            throw new InvalidJsonException('Property "jsonrpc" must be set to "2.0"');
        }

        if (!is_object($data->error)){
            throw new InvalidJsonException('Property "error" must be an object');
        } else {
            if (!property_exists($data->error, 'code')){
                throw new InvalidJsonException('Property "code" is required');
            }
            if (!property_exists($data->error, 'message')){
                throw new InvalidJsonException('Property "message" is required');
            }
        }

        $details = [];
        if (property_exists($data, 'data')){
            $details = $data->params;
        }

        $id = null;
        if (property_exists($data, 'id')){
            $id = $data->id;
        }

        return new self($id, $data->error->code, $data->error->message, $details);
    }

    public static function createFromException($id, \Exception $exception){
        $data = null;
        if ($exception instanceof JSONRPCException){
            $data = $exception->getData();
        }

        return new self($id, $exception->getCode(), $exception->getMessage(), $data);
    }
}
