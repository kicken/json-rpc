<?php

namespace Kicken\JSONRPC\Exception;

use JetBrains\PhpStorm\Pure;

class UnableToConnectException extends \RuntimeException {
    #[Pure]
    public function __construct(public readonly string $url, public readonly int $streamErrorCode, public readonly string $streamErrorMessage){
        parent::__construct('Unable to connect to server');
    }
}
