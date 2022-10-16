<?php

namespace Kicken\JSONRPC;

interface RPCMethod {
    public function getName();

    public function run(Request $request);
}
