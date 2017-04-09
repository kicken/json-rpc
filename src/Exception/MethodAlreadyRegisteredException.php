<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:57 PM
 */

namespace Kicken\JSONRPC\Exception;

class MethodAlreadyRegisteredException extends \LogicException {
    public function __construct($method){
        parent::__construct(sprintf('Method "%s" has already been registered with this server.', $method));
    }
}