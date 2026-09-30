<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Models;

class State
{
    public function __construct(private string $name, private bool $isCurrent = false)
    {
    }

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
