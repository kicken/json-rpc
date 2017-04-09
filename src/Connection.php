<?php
/**
 * Created by PhpStorm.
 * User: Keith
 * Date: 4/8/2017
 * Time: 5:49 PM
 */

namespace Kicken\JSONRPC;


use Evenement\EventEmitterTrait;
use Kicken\JSONRPC\Exception\MethodNotFoundException;
use React\Socket\ConnectionInterface;

class Connection {
    use EventEmitterTrait;
    protected $stream = null;
    protected $buffer = '';
    protected $methods = [];

    public function __construct(ConnectionInterface $connection, $methods){
        $this->stream = $connection;
        $this->methods = $methods;
        $this->stream->on('data', function($data){
            $this->handleData($data);
        });
        $this->stream->on('end', function(){
            $this->handleData();
        });
    }

    protected function handleData($data = ''){
        $this->buffer .= $data;
        if ($this->buffer !== ''){
            $this->parseBuffer();
        }
    }

    protected function parseBuffer(){
        $keepGoing = true;
        while ($keepGoing){
            $documentString = $this->extractJsonDocument();
            $document = json_decode($documentString);
            $error = json_last_error();
            if ($error === JSON_ERROR_NONE){
                $this->process($document);
                $this->buffer = substr($this->buffer, strlen($documentString));
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

    protected function process($requestList){
        $batchMode = is_array($requestList);
        if (!$batchMode){
            $requestList = [$requestList];
        }

        $responseList = [];
        foreach ($requestList as $requestData){
            $response = $this->dispatchRequest($requestData);
            if ($response){
                $responseList[] = $response;
            }
        }

        if (!$batchMode && count($responseList) > 0){
            $responseList = $responseList[0];
        }

        if ($responseList){
            $this->sendResponse($responseList);
        }
    }

    protected function sendResponse($response){
        $responseJson = json_encode($response);
        $this->stream->write($responseJson . "\r\n");
    }

    protected function dispatchRequest($requestData){
        /** @noinspection PhpUnusedLocalVariableInspection */
        $request = $response = null;
        try {
            $request = new Request($requestData);
            $callback = $this->getMethodCallback($request->getMethod());
            $result = call_user_func($callback, $request);
            $response = new Response($request->getId(), $result);
        } catch (\Exception $ex){
            $id = $request?$request->getId():null;
            $response = new ErrorResponse($id, $ex);
        }

        if ($request && $request->isNotification()){
            $response = null;
        }

        return $response;
    }

    protected function getMethodCallback($method){
        if (!array_key_exists($method, $this->methods)){
            throw new MethodNotFoundException($method);
        }

        return $this->methods[$method];
    }
}
