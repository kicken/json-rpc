<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:32 PM
 */

namespace Kicken\JSONRPC\Exception;

use RuntimeException;

class JSONRPCException extends RuntimeException {
    protected mixed $data;

    public function __construct(string $message = "", int $code = 0, $data = null){
        parent::__construct($message, $code);
        $this->data = $data;
    }

    public function getData(){
        return $this->data;
    }
}
