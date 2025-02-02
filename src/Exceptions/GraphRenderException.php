<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class GraphRenderException extends \RuntimeException
{
    public static function fromJsonError(string $message): self
    {
        return new self("Failed to render graph due to invalid JSON: {$message}");
    }

    public static function fromRenderError(string $message): self
    {
        return new self("Failed to render graph: {$message}");
    }
}
