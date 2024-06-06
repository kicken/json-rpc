<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:47 PM
 */

namespace Kicken\JSONRPC;


use InvalidArgumentException;
use JsonSerializable;
use Kicken\JSONRPC\Exception\InvalidJsonException;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class Request implements JsonSerializable {
    public function __construct(
        private readonly string $method,
        private readonly array|object|null $params = null,
        private readonly ?string $id = null,
        private readonly bool $isNotification = false
    ){
        if ($params !== null){
            if (!is_array($params) && !is_object($params)){
                throw new InvalidArgumentException('Params must be an array or object');
            }
        }
    }

    public static function createFromJsonObject(object $data) : Request{
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

    public static function createFromHttpRequest(RequestInterface $request) : Request{
        if ($request->getHeaderLine('Content-type') !== 'application/json'){
            throw new RuntimeException('Invalid type');
        }
        $body = $request->getBody()->getContents();
        $decoded = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE){
            throw new RuntimeException('Invalid json data');
        }

        return self::createFromJsonObject($decoded);
    }

    public function jsonSerialize() : array{
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

    public function isNotification() : bool{
        return $this->isNotification;
    }

    public function getId() : ?string{
        return $this->id;
    }

    public function getMethod() : string{
        return $this->method;
    }

    public function getParams() : object|array|null{
        return $this->params;
    }
}
