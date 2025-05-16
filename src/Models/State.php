<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Models;

class State
{
    private bool $isCurrent = false;

    public function __construct(private string $name)
    {
    }

    public function setIsCurrent(bool $isCurrent): self
    {
        $this->isCurrent  = $isCurrent;

        return $this;
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
