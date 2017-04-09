<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 7:11 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\JSONRPCException;

class ErrorResponse extends Response {
    private $exception;

    public function __construct($id, \Exception $exception){
        parent::__construct($id, false);
        $this->exception = $exception;
    }

    public function jsonSerialize(){
        $result = parent::jsonSerialize();
        unset($result['result']);

        $ex = $this->exception;
        $result['error'] = [
            'code' => $ex->getCode()
            , 'message' => $ex->getMessage()
        ];

        if ($ex instanceof JSONRPCException){
            $result['error']['data'] = $ex->getData();
        }

        return $result;
    }
}