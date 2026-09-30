<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class UnknownStateException extends StateMachineException
{
    public static function forState(string $state): self
    {
        return new self("State '{$state}' does not exist in the state machine");
    }

    public static function noInitialState(): self
    {
        return new self('The subject has no state and the state machine has no initial state (no state has isCurrent)');
    }
}
