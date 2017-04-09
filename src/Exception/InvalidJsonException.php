<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:16 PM
 */

namespace Kicken\JSONRPC\Exception;

class InvalidJsonException extends JSONRPCException {
    public function __construct($details){
        parent::__construct('JSON format is invalid.', -32600, $details);
    }
}