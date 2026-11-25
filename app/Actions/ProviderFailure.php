<?php

namespace App\Actions;

use RuntimeException;

class ProviderFailure extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly ?int $retryAt = null)
    {
        parent::__construct($message);
    }
}
