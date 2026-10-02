<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class MachineRegistryException extends StateMachineException
{
    public static function unknown(string $name): self
    {
        return new self("State machine '{$name}' is not registered");
    }

    public static function alreadyRegistered(string $name): self
    {
        return new self("A state machine named '{$name}' is already registered");
    }
}
