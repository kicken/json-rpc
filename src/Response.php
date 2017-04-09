<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 7:10 PM
 */

namespace Kicken\JSONRPC;


class Response implements \JsonSerializable {
    private $id;
    private $result;

    public function __construct($id, $result){
        $this->id = $id;
        $this->result = $result;
    }

    public function jsonSerialize(){
        return [
            'jsonrpc' => '2.0'
            , 'result' => $this->result
            , 'id' => $this->id
        ];
    }
}