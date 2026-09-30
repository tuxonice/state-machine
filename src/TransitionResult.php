<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

class TransitionResult
{
    public function __construct(private TransitionStatus $status, private string $state)
    {
    }

    public function getStatus(): TransitionStatus
    {
        return $this->status;
    }

    /**
     * The state after applying the event: the target when moved, otherwise the unchanged current state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    public function hasMoved(): bool
    {
        return $this->status === TransitionStatus::Moved;
    }
}
