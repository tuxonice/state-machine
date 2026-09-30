<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Storage\StatefulInterface;

class Order implements StatefulInterface
{
    public function __construct(private ?string $state = null)
    {
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }
}
