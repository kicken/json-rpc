<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:24 PM
 */

namespace Kicken\JSONRPC\Exception;

class MethodNotFoundException extends JSONRPCException {
    public function __construct($method){
        parent::__construct('Method is not registered with this server.', -32601, $method);
    }
}