<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 3:03 AM
 */

namespace Kicken\JSONRPC;


use Evenement\EventEmitterTrait;
use Kicken\JSONRPC\Exception\MalformedJsonException;
use React\Stream\ReadableStreamInterface;

class JSONReader {
    use EventEmitterTrait;

    /** @var ReadableStreamInterface */
    protected $stream = null;
    /** @var string */
    protected $buffer = '';

    public function __construct(ReadableStreamInterface $stream){
        $this->stream = $stream;
        $this->stream->on('data', function($data){
            $this->buffer .= $data;
            $this->parseBuffer();
        });
    }

    protected function parseBuffer(){
        $keepGoing = true;
        while ($keepGoing){
            $documentString = $this->extractJsonDocument();
            $document = json_decode($documentString);
            $error = json_last_error();
            if ($error === JSON_ERROR_NONE){
                $this->buffer = substr($this->buffer, strlen($documentString));
                $this->emit('data', [$document]);
            } else {
                $keepGoing = false;
                if ($error !== JSON_ERROR_SYNTAX || !$this->stream->isReadable()){
                    $this->buffer = '';
                    $this->emit('error', [new MalformedJsonException(json_last_error_msg())]);
                }
            }
        }
    }

    protected function extractJsonDocument(){
        $document = '';
        $keepGoing = true;
        $inQuote = false;
        $braceCounter = 0;
        $bracketCounter = 0;
        $i = 0;
        while ($keepGoing && isset($this->buffer[$i])){
            $previousCh = $i > 0?$this->buffer[$i - 1]:null;
            $ch = $this->buffer[$i++];
            $document .= $ch;

            if (!ctype_space($ch)){
                if ($ch == '"' && (!$inQuote || $previousCh !== '\\')){
                    $inQuote = !$inQuote;
                } else if (!$inQuote){
                    if ($ch == '{'){
                        $braceCounter++;
                    } else if ($ch == '}'){
                        $braceCounter--;
                    } else if ($ch == '['){
                        $bracketCounter++;
                    } else if ($ch == ']'){
                        $bracketCounter--;
                    }
                }

                if ($braceCounter == 0 && $bracketCounter == 0 && !$inQuote){
                    $keepGoing = false;
                }
            }
        }

        return $document;
    }
}