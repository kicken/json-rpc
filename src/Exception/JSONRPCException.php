<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:32 PM
 */

namespace Kicken\JSONRPC\Exception;

class JSONRPCException extends \RuntimeException {
    protected $data;

    /**
     * JSONRPCException constructor.
     *
     * @param string $message Brief message describing the error
     * @param int $code Error code value
     * @param mixed $data Extra data or details related to this error
     */
    public function __construct($message = "", $code = 0, $data = null){
        parent::__construct($message, $code);
        $this->data = $data;
    }

    public function getData(){
        return $this->data;
    }
}