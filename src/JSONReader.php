<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/9/2017
 * Time: 3:03 AM
 */

namespace Kicken\JSONRPC;


use Generator;
use Kicken\JSONRPC\Exception\MalformedJsonException;
use LogicException;
use stdClass;

class JSONReader {
    private bool $bufferCanGrow = true;
    private string $buffer = '';
    private int $bufferOffset = 0;

    public function feed(string $data, bool $expectMore = true) : void{
        $this->buffer .= $data;
        $this->bufferCanGrow = $expectMore;
    }

    public function reset() : void{
        $this->buffer = '';
    }

    public function readObjects() : Generator{
        while ($this->findStartOfDocument()){
            $startOffset = $this->bufferOffset;
            if ($this->findEndOfDocument()){
                $endOffset = $this->bufferOffset + 1;
                $document = substr($this->buffer, $startOffset, $endOffset - $startOffset);
                $this->buffer = substr($this->buffer, $endOffset + 1);

                yield $this->processJsonDocument($document);
            } else if (!$this->bufferCanGrow){
                throw new MalformedJsonException();
            } else {
                return;
            }
        }
    }

    private function processJsonDocument($document) : stdClass|array{
        $data = json_decode($document);
        $error = json_last_error();
        if ($error === JSON_ERROR_NONE){
            return $data;
        } else {
            throw new MalformedJsonException();
        }
    }

    private function findStartOfDocument() : bool{
        $this->bufferOffset = 0;
        for (; isset($this->buffer[$this->bufferOffset]); $this->bufferOffset++){
            $ch = $this->buffer[$this->bufferOffset];
            if (in_array($ch, ['[', '{'])){
                return true;
            } else if (!ctype_space($ch)){
                throw new MalformedJsonException();
            }
        }

        return false;
    }

    private function findEndOfDocument() : bool{
        $openingChar = $this->buffer[$this->bufferOffset];
        $closingChar = match ($openingChar) {
            '{' => '}',
            '[' => ']',
            default => throw new LogicException('Invalid opening document character ' . $openingChar)
        };

        $inQuote = false;
        $previousCh = null;
        $braceCounter = $bracketCounter = 0;

        for (; isset($this->buffer[$this->bufferOffset]); $this->bufferOffset++){
            $ch = $this->buffer[$this->bufferOffset];
            if ($ch === '\\' && $previousCh !== '\\'){
                $previousCh = null;
                continue;
            } else if ($ch == '"' && $previousCh !== '\\'){
                $inQuote = !$inQuote;
            } else if (!$inQuote){
                if ($ch === '{'){
                    $braceCounter++;
                } else if ($ch === '}'){
                    $braceCounter--;
                } else if ($ch === '['){
                    $bracketCounter++;
                } else if ($ch === ']'){
                    $bracketCounter--;
                }

                if ($ch === $closingChar && $braceCounter === 0 && $bracketCounter === 0){
                    return true;
                }
            }

            $previousCh = $ch;
        }

        return false;
    }
}
