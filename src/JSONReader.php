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

    public function feed(string $data, bool $expectMore = true) : void{
        $this->buffer .= $data;
        $this->bufferCanGrow = $expectMore;
    }

    public function reset() : void{
        $this->buffer = '';
    }

    public function readObjects() : Generator{
        while (null !== $startOffset = $this->findStartOfDocument()){
            if (null !== $endOffset = $this->findEndOfDocument($startOffset)){
                $endOffset++;
                $document = substr($this->buffer, $startOffset, $endOffset - $startOffset);
                $this->buffer = substr($this->buffer, $endOffset);

                yield $this->processJsonDocument($document);
            } else if (!$this->bufferCanGrow){
                throw new MalformedJsonException(json_last_error());
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
            throw new MalformedJsonException($error);
        }
    }

    private function findStartOfDocument() : ?int{
        $startOfDocument = $this->findFirstCharacter(['[', '{']);
        if ($startOfDocument === null){
            if (ltrim($this->buffer) !== ''){
                throw new MalformedJsonException(JSON_ERROR_SYNTAX);
            }
        }

        return $startOfDocument;
    }

    private function findEndOfDocument(int $startOffset) : ?int{
        $openingChar = $this->buffer[$startOffset];
        $closingChar = match ($openingChar) {
            '{' => '}',
            '[' => ']',
            default => throw new LogicException('Invalid opening document character ' . $openingChar)
        };
        $inQuote = false;
        $braceCounter = $bracketCounter = 0;
        $startOffset++;

        do {
            $charOffset = $this->findFirstCharacter(['"', '[', '{', '}', ']'], $startOffset);
            $char = $this->buffer[$charOffset] ?? null;
            if (!$inQuote && $char === $closingChar && $braceCounter === 0 && $bracketCounter === 0){
                return $charOffset;
            }

            if ($char === '"' && !$this->isEscaped($charOffset)){
                $inQuote = !$inQuote;
            } else if (!$inQuote){
                if ($char === '{'){
                    $braceCounter++;
                } else if ($char === '}'){
                    $braceCounter--;
                } else if ($char === '['){
                    $bracketCounter++;
                } else if ($char === ']'){
                    $bracketCounter--;
                }
            }

            $startOffset = $charOffset + 1;
        } while ($charOffset !== null);

        return null;
    }

    private function findFirstCharacter(array $charList, int $baseOffset = 0) : ?int{
        $positions = array_map(function(string $char) use ($baseOffset) : ?int{
            $pos = strpos($this->buffer, $char, $baseOffset);

            return $pos === false ? null : $pos;
        }, $charList);

        $positions = array_filter($positions, fn(?int $position) => $position !== null);

        return count($positions) ? min($positions) : null;
    }

    private function isEscaped(int $charOffset) : bool{
        $slashCount = 0;
        $previousCharOffset = $charOffset - 1;
        while (($this->buffer[$previousCharOffset] ?? '') === '\\'){
            $slashCount++;
            $previousCharOffset--;
        }

        if ($slashCount & 1){
            return true;
        } else {
            return false;
        }
    }
}
