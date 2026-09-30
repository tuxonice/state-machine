<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

use InvalidArgumentException;

class UnknownEventException extends InvalidArgumentException
{
    public static function forEvent(string $event): self
    {
        return new self("Event '{$event}' does not exist in the state machine");
    }
}
