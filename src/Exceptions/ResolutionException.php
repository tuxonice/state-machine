<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class ResolutionException extends StateMachineException
{
    public static function classNotFound(string $class): self
    {
        return new self("Class '{$class}' does not exist");
    }

    public static function wrongType(string $class, string $interface): self
    {
        return new self("Class '{$class}' must implement {$interface}");
    }
}
