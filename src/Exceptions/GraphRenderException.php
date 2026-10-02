<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class GraphRenderException extends StateMachineException
{
    public static function fromJsonError(string $message): self
    {
        return new self("Failed to render graph due to invalid JSON: {$message}");
    }

    public static function missingDependency(string $package): self
    {
        return new self("Diagrams need the optional package {$package}, install it with: composer require {$package}");
    }

    public static function fromRenderError(string $message): self
    {
        return new self("Failed to render graph: {$message}");
    }
}
