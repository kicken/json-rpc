<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:20 PM
 */

namespace Kicken\JSONRPC\Exception;


class MalformedJsonException extends \RuntimeException {
    public function __construct($error){
        parent::__construct('Malformed JSON received.', -32700, $error);
    }
}