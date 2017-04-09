<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 7:10 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\InvalidJsonException;

class Response implements \JsonSerializable {
    private $id;
    private $result;

    public function __construct($id, $result){
        $this->id = $id;
        $this->result = $result;
    }

    public static function createFromJsonObject($data){
        if (!property_exists($data, 'jsonrpc')){
            throw new InvalidJsonException('Missing required property "jsonrpc"');
        }

        if (!property_exists($data, 'result')){
            throw new InvalidJsonException('Missing required property "result"');
        }

        if ($data->jsonrpc !== '2.0'){
            throw new InvalidJsonException('Property "jsonrpc" must be set to "2.0"');
        }

        $id = null;
        if (property_exists($data, 'id')){
            $id = $data->id;
        }

        return new self($id, $data->result);
    }

    public function jsonSerialize(){
        return [
            'jsonrpc' => '2.0'
            , 'result' => $this->result
            , 'id' => $this->id
        ];
    }
}