<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 3:03 AM
 */

namespace Kicken\JSONRPC;


use Evenement\EventEmitterTrait;
use Kicken\JSONRPC\Exception\InvalidJsonException;
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
            try {
                $document = $this->extractJsonDocument();
                if (trim($document) === ''){
                    $keepGoing = false;
                } else {
                    $this->processJsonDocument($document);
                }
            } catch (MalformedJsonException $ex){
                $keepGoing = false;
                if ($ex->getJsonErrorCode() !== JSON_ERROR_SYNTAX || !$this->stream->isReadable()){
                    $this->buffer = '';
                    $this->emit('error', [$ex]);
                }
            } catch (InvalidJsonException $ex){
                $keepGoing = false;
                $this->buffer = '';
                $this->emit('error', [$ex]);
            }
        }
    }

    private function processJsonDocument($document){
        $data = json_decode($document);
        $error = json_last_error();
        if ($error === JSON_ERROR_NONE){
            $this->buffer = substr($this->buffer, strlen($document));
            $this->emit('data', [$data]);
        } else {
            throw new MalformedJsonException();
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


        $firstCharacter = substr(trim($document), 0, 1);
        if ($firstCharacter !== false && $firstCharacter != '[' && $firstCharacter != '{'){
            throw new InvalidJsonException("Stream cannot contain data outside an array or object container");
        }

        return $document;
    }
}