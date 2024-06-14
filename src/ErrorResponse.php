<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 7:11 PM
 */

namespace Kicken\JSONRPC;


use Exception;
use Kicken\JSONRPC\Exception\InvalidJsonException;
use Kicken\JSONRPC\Exception\JSONRPCException;

class ErrorResponse implements Response {
    public const GENERIC_ERROR_CODE = -32603;

    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public mixed $data = null,
        public readonly ?string $id = null,
    ){
    }

    public static function createFromJsonObject($data) : ErrorResponse{
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

        return new self($data->error->code, $data->error->message, $details, $id);
    }

    public static function createFromException(Exception $exception, ?string $id = null) : ErrorResponse{
        $data = null;
        if ($exception instanceof JSONRPCException){
            $data = $exception->getData();
        }

        $code = $exception->getCode() ?? -self::GENERIC_ERROR_CODE;
        $message = $exception->getMessage();
        if (!$message && $code === self::GENERIC_ERROR_CODE){
            $message = 'Internal Error';
        }

        return new self($code, $message, $data, $id);
    }

    public function jsonSerialize() : array{
        $result = [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'error' => [
                'code' => $this->code,
                'message' => $this->message
            ]
        ];
        if ($this->data){
            $result['error']['data'] = $this->data;
        }

        return $result;
    }

    public function getId() : ?string{
        return $this->id;
    }
}
