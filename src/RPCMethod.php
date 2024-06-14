<?php

namespace Kicken\JSONRPC;

use Kicken\JSONRPC\Server\ClientSession;

interface RPCMethod {
    public function getName() : string;

    public function run(Request $request, ClientSession $session) : mixed;
}
