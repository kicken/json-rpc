<?php

namespace Kicken\JSONRPC\Tests\unit;

use Kicken\JSONRPC\Exception\MalformedJsonException;
use Kicken\JSONRPC\JSONReader;
use PHPUnit\Framework\TestCase;
use stdClass;

class JSONReaderTest extends TestCase {
    private JSONReader $service;

    protected function setUp() : void{
        $this->service = new JSONReader();
    }


    public function testExtractsJsonObject(){
        $this->service->feed('{"test":"object"}');
        $result = iterator_to_array($this->service->readObjects());

        $this->assertCount(1, $result);
        $this->assertObjectHasProperty("test", $result[0]);
        $this->assertEquals("object", $result[0]->test);
    }

    public function testExtractsJsonArray(){
        $this->service->feed('[{"test":"object"},{"test":"object 2"}]');
        [$result] = iterator_to_array($this->service->readObjects());

        $this->assertCount(2, $result);
    }

    public function testExtractsJsonObjectSurroundedBySpaces(){
        $this->service->feed('  {"test":"object"}  ');
        [$result] = iterator_to_array($this->service->readObjects());

        $this->assertObjectHasProperty("test", $result);
        $this->assertEquals("object", $result->test);
    }

    public function testExtractsJsonArraySurroundedBySpaces(){
        $this->service->feed('  [ {"test":"object"} , {"test":"object 2"} ]  ');
        [$result] = iterator_to_array($this->service->readObjects());

        $this->assertCount(2, $result);
    }

    public function testYieldsNothingOnEmptyBufferThatCanGrow(){
        $this->assertEmpty(iterator_to_array($this->service->readObjects()));
    }

    public function testThrowsOnIncompleteJsonThatCannotGrow(){
        $this->expectException(MalformedJsonException::class);

        $this->service->feed('{"test":"object', false);
        iterator_to_array($this->service->readObjects());
    }

    public function testThrowsOnInvalidBuffer(){
        $this->service->feed('Invalid Json Data');
        $this->expectException(MalformedJsonException::class);

        iterator_to_array($this->service->readObjects());
    }

    public function testThrowsOnInvalidBufferThatStartWithSpaces(){
        $this->service->feed('    Invalid Json Data');
        $this->expectException(MalformedJsonException::class);

        iterator_to_array($this->service->readObjects());
    }

    public function testHandlesEscapedSlashBeforeQuote(){
        $this->service->feed('{"test":"object\\\\"}');
        [$result] = iterator_to_array($this->service->readObjects());
        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testHandlesEscapedQuoteInString(){
        $this->service->feed('{"test":"object\"1\""}');
        [$result] = iterator_to_array($this->service->readObjects());
        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testHandlesNestedArray(){
        $this->service->feed('{"jsonrpc": "2.0", "method": "sum", "params": [1,2,4], "id": "1"}');
        [$result] = iterator_to_array($this->service->readObjects());
        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testHandlesNestedObjects(){
        $this->service->feed('{"jsonrpc": "2.0", "method": "foo.get", "params": {"name": "myself"}, "id": "5"}');
        [$result] = iterator_to_array($this->service->readObjects());
        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testHandlesBatchArray(){
        $this->service->feed('[
            {"jsonrpc": "2.0", "method": "sum", "params": [1,2,4], "id": "1"},
            {"jsonrpc": "2.0", "method": "notify_hello", "params": [7]},
            {"jsonrpc": "2.0", "method": "subtract", "params": [42,23], "id": "2"},
            {"foo": "boo"},
            {"jsonrpc": "2.0", "method": "foo.get", "params": {"name": "myself"}, "id": "5"},
            {"jsonrpc": "2.0", "method": "get_data", "id": "9"} 
        ]');
        [$result] = iterator_to_array($this->service->readObjects());
        $this->assertCount(6, $result);
    }

    public function testHandlesObjectSplitAcrossFeeds(){
        $this->service->feed('[
            {"jsonrpc": "2.0", "method": "sum", "params": [1,2,4], "id": "1"},
        ');
        [$result] = iterator_to_array($this->service->readObjects());
        $this->assertEmpty($result);
        $this->service->feed('
            {"jsonrpc": "2.0", "method": "notify_hello", "params": [7]},
            {"jsonrpc": "2.0", "method": "subtract", "params": [42,23], "id": "2"},
            {"foo": "boo"},
            {"jsonrpc": "2.0", "method": "foo.get", "params": {"name": "myself"}, "id": "5"},
            {"jsonrpc": "2.0", "method": "get_data", "id": "9"} 
        ]');
        [$result] = iterator_to_array($this->service->readObjects());
        $this->assertCount(6, $result);
    }

    public function testHandlesMultipleObjects(){
        $this->service->feed(implode('', [
            '{"jsonrpc": "2.0", "method": "sum", "params": [1,2,4], "id": "1"}',
            '{"jsonrpc": "2.0", "method": "notify_hello", "params": [7]}',
            '{"jsonrpc": "2.0", "method": "subtract", "params": [42,23], "id": "2"}',
            '{"foo": "boo"}',
            '{"jsonrpc": "2.0", "method": "foo.get", "params": {"name": "myself"}, "id": "5"}',
            '{"jsonrpc": "2.0", "method": "get_data", "id": "9"}',
        ]));
        $result = iterator_to_array($this->service->readObjects());
        $this->assertCount(6, $result);
    }
}
