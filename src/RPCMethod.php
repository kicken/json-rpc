<?php

namespace Kicken\JSONRPC;

interface RPCMethod {
    public function getName() : string;

    public function run(Request $request) : object|array|null;
}
