<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

use InvalidArgumentException;

class UnknownStateException extends InvalidArgumentException
{
    public static function forState(string $state): self
    {
        return new self("State '{$state}' does not exist in the state machine");
    }
}
