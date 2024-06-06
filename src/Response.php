<?php

namespace Kicken\JSONRPC;

use JsonSerializable;

interface Response extends JsonSerializable {
    public function getId() : ?string;
}
