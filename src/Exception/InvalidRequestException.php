<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:16 PM
 */

namespace Kicken\JSONRPC\Exception;

class InvalidRequestException extends \RuntimeException {
    public function __construct($details){
        parent::__construct('Request format is invalid.', -32600);
    }
}