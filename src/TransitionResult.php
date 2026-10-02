<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

class TransitionResult
{
    /**
     * @param string[] $events Events applied, the requested one first, then any onEnter ones
     */
    public function __construct(private TransitionStatus $status, private string $state, private array $events = [])
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

    /**
     * Events applied in order: the requested one, then the onEnter events it triggered.
     *
     * @return string[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function hasMoved(): bool
    {
        return $this->status === TransitionStatus::Moved;
    }
}
