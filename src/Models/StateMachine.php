<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Models;

class StateMachine
{
    private string $name;

    /**
     * @var array<\Tlab\StateMachine\Models\Event>
     */
    private array $events = [];

    /**
     * @var array<\Tlab\StateMachine\Models\Transition>
     */
    private array $transitions = [];

    /**
     * @var array<\Tlab\StateMachine\Models\State>
     */
    private array $states = [];

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function addEvent(Event $event): self
    {
        $this->events[] = $event;

        return $this;
    }

    public function addTransition(Transition $transition): self
    {
        $this->transitions[] = $transition;

        return $this;
    }

    public function addState(State $state): self
    {
        $this->states[] = $state;

        return $this;
    }

    /**
     * @return Event[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @return Transition[]
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * @return State[]
     */
    public function getStates(): array
    {
        return $this->states;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
