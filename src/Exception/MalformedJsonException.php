<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 6:20 PM
 */

namespace Kicken\JSONRPC\Exception;

!defined('JSON_ERROR_RECURSION') && define('JSON_ERROR_RECURSION', 6);
!defined('JSON_ERROR_INF_OR_NAN') && define('JSON_ERROR_INF_OR_NAN', 7);
!defined('JSON_ERROR_UNSUPPORTED_TYPE') && define('JSON_ERROR_UNSUPPORTED_TYPE', 8);
!defined('JSON_ERROR_INVALID_PROPERTY_NAME') && define('JSON_ERROR_INVALID_PROPERTY_NAME', 9);
!defined('JSON_ERROR_UTF16') && define('JSON_ERROR_UTF16', 10);


class MalformedJsonException extends JSONRPCException {
    public function __construct(int $code){
        $codeMessage = $this->codeToMessage($code);

        parent::__construct('Malformed JSON received. ' . $codeMessage, -32700, [
            'code' => $code
            , 'message' => $codeMessage
        ]);
    }

    public function getJsonErrorCode(){
        $data = $this->getData();

        return $data['code'];
    }

    public function getJsonErrorMessage(){
        $data = $this->getData();

        return $data['message'];
    }

    public function __toString() : string{
        return parent::__toString();
    }

    private function codeToMessage($code) : string{
        return match ($code) {
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded',
            JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded',
            JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given',
            JSON_ERROR_INVALID_PROPERTY_NAME => 'A property name that cannot be encoded was given',
            JSON_ERROR_UTF16 => 'Malformed UTF-16 characters, possibly incorrectly encoded',
            default => 'Syntax error',
        };
    }
}
