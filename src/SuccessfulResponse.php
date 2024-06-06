<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 7:10 PM
 */

namespace Kicken\JSONRPC;


use Kicken\JSONRPC\Exception\InvalidJsonException;

class SuccessfulResponse implements Response {
    public function __construct(
        public readonly ?string $id,
        public readonly object|array|null $result = null
    ){
    }

    public static function createFromJsonObject(object $data) : SuccessfulResponse{
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

    public function jsonSerialize() : array{
        return [
            'jsonrpc' => '2.0'
            , 'result' => $this->result
            , 'id' => $this->id
        ];
    }

    public function getId() : ?string{
        return $this->id;
    }
}
