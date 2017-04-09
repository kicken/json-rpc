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
    public function __construct(){
        $code = json_last_error();

        parent::__construct('Malformed JSON received.', -32700, [
            'code' => $code
            , 'message' => $this->codeToMessage($code)
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

    private function codeToMessage($code){
        switch ($code){
            case JSON_ERROR_DEPTH:
                return 'The maximum stack depth has been exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Invalid or malformed JSON';
            case JSON_ERROR_CTRL_CHAR:
                return 'Control character error, possibly incorrectly encoded';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            case JSON_ERROR_RECURSION:
                return 'One or more recursive references in the value to be encoded';
            case JSON_ERROR_INF_OR_NAN:
                return 'One or more NAN or INF values in the value to be encoded';
            case JSON_ERROR_UNSUPPORTED_TYPE:
                return 'A value of a type that cannot be encoded was given';
            case JSON_ERROR_INVALID_PROPERTY_NAME:
                return 'A property name that cannot be encoded was given';
            case JSON_ERROR_UTF16:
                return 'Malformed UTF-16 characters, possibly incorrectly encoded';
            default:
                return 'Unknown json error';
        }
    }
}