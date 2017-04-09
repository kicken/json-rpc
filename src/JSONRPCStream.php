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
use React\EventLoop\LoopInterface;
use React\Stream\DuplexStreamInterface;

class JSONRPCStream {
    use EventEmitterTrait;

    protected $stream;
    protected $loop;
    protected $buffer;

    public function __construct(DuplexStreamInterface $stream, LoopInterface $loop){
        $this->stream = $stream;
        $this->loop = $loop;

        $this->stream->on('data', function ($data){
            $this->buffer .= $data;
            $this->parseBuffer();
        });
    }

    public function sendRequest(Request $request){
        $this->stream->write(json_encode($request));
    }

    public function sendResponse(Response $response){
        $this->stream->write(json_encode($response));
    }

    protected function parseBuffer(){
        $keepGoing = true;
        while ($keepGoing){
            $documentString = $this->extractJsonDocument();
            $document = json_decode($documentString);
            $error = json_last_error();
            if ($error === JSON_ERROR_NONE){
                $this->buffer = substr($this->buffer, strlen($documentString));
                $this->process($document);
            } else {
                $keepGoing = false;
                if ($error !== JSON_ERROR_SYNTAX || !$this->stream->isReadable()){
                    $this->buffer = '';
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

    protected function process($document){
        $batchMode = is_array($document);
        if (!$batchMode){
            $document = [$document];
        }

        $responseList = [];
        foreach ($document as $item){
            /** @var Request|Response $object */
            $object = null;
            try {
                $object = $this->convertToObject($item);
                if ($object instanceof Request){

                } else if ($object instanceof Response){

                }
            } catch (\Exception $ex){
                $id = $object?$object->getId():null;
                $responseList[] = ErrorResponse::createFromException($id, $ex);
            }
        }

        if (!$batchMode && count($responseList) > 0){
            $responseList = $responseList[0];
        }

        if ($responseList){
            $this->sendResponse($responseList);
        }
    }

    private function convertToObject($data){
        if (property_exists($data, 'method')){
            return Request::createFromJsonObject($data);
        }

        if (property_exists($data, 'result')){
            return Response::createFromJsonObject($data);
        }

        if (property_exists($data, 'error')){
            return ErrorResponse::createFromJsonObject($data);
        }

        throw new InvalidJsonException('Unknown json document type.');
    }
}