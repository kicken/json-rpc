<?php

namespace Kicken\JSONRPC\Server;

class ClientSession implements \ArrayAccess {
    private array $storage = [];

    public function __construct(public readonly string $endpoint){
    }

    public function set(string $key, mixed $value) : void{
        $this->storage[$key] = $value;
    }

    public function get(string $key) : mixed{
        return $this->storage[$key] ?? null;
    }

    public function has(string $key) : bool{
        return array_key_exists($key, $this->storage);
    }

    public function delete(string $key) : void{
        unset($this->storage[$key]);
    }

    public function offsetExists(mixed $offset) : bool{
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset) : mixed{
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value) : void{
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset) : void{
        $this->delete($offset);
    }
}
