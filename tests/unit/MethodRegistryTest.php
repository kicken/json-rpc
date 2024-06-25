<?php

namespace Kicken\JSONRPC\Tests\unit;

use Kicken\JSONRPC\MethodRegistry;
use Kicken\JSONRPC\Request;
use Kicken\JSONRPC\Response;
use Kicken\JSONRPC\RPCMethod;
use Kicken\JSONRPC\Server\ClientSession;
use Kicken\JSONRPC\SuccessfulResponse;
use PHPUnit\Framework\TestCase;
use TypeError;

class MethodRegistryTest extends TestCase {
    private RPCMethod $testMethod;

    protected function setUp() : void{
        $this->testMethod = new class implements RPCMethod {
            public function getName() : string{
                return 'test_method';
            }

            public function run(Request $request, ClientSession $session) : Response{
                return new SuccessfulResponse($request->getId());
            }
        };
    }

    public function testThrowsIfConstructedWithInvalidArray(){
        $this->expectException(TypeError::class);
        new MethodRegistry(['hello']);
    }

    public function testConstructorMethodsGetRegistered(){
        $registry = new MethodRegistry([$this->testMethod]);
        $this->assertTrue($registry->isRegistered($this->testMethod->getName()));
    }

    public function testUnregisterIsSuccessful(){
        $registry = new MethodRegistry([$this->testMethod]);
        $registry->unregister($this->testMethod->getName());
        $this->assertFalse($registry->isRegistered($this->testMethod->getName()));
    }

    public function testRegisterIsSuccessful(){
        $registry = new MethodRegistry();
        $registry->register($this->testMethod);
        $this->assertTrue($registry->isRegistered($this->testMethod->getName()));
    }
}
