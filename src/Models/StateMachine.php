<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Models;

class StateMachine
{
    /**
     * @param string $name
     * @param array<State> $states
     * @param array<Transition> $transitions
     * @param array<Event> $events
     */
    public function __construct(
        private readonly string $name,
        private readonly array $states,
        private readonly array $transitions,
        private readonly array $events,
    ) {
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

    public function hasState(string $name): bool
    {
        foreach ($this->states as $state) {
            if ($state->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function hasEvent(string $name): bool
    {
        return $this->getEvent($name) !== null;
    }

    public function getEvent(string $name): ?Event
    {
        foreach ($this->events as $event) {
            if ($event->getName() === $name) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Transitions leaving the state for the event, in definition order.
     *
     * @return Transition[]
     */
    public function getTransitionsFor(string $state, string $event): array
    {
        return array_values(array_filter(
            $this->transitions,
            fn(Transition $transition) => $transition->getSource() === $state
                && $transition->getEvent() === $event
        ));
    }

    /**
     * Distinct events that have a transition leaving the state, in definition order.
     * Conditions are not evaluated.
     *
     * @return string[]
     */
    public function getAvailableEvents(string $state): array
    {
        $events = [];
        foreach ($this->transitions as $transition) {
            if ($transition->getSource() === $state) {
                $events[$transition->getEvent()] = true;
            }
        }

        return array_keys($events);
    }

    /**
     * Events with an onEnter flag that have a transition leaving the state, in event definition order.
     *
     * @return Event[]
     */
    public function getOnEnterEvents(string $state): array
    {
        return $this->availableEventsWhere($state, fn(Event $event) => $event->isOnEnter());
    }

    /**
     * Events with a manual flag that have a transition leaving the state, in event definition order.
     *
     * @return Event[]
     */
    public function getManualEvents(string $state): array
    {
        return $this->availableEventsWhere($state, fn(Event $event) => $event->isManual());
    }

    /**
     * Events with a timeout that have a transition leaving the state, in event definition order.
     *
     * @return Event[]
     */
    public function getTimeoutEvents(string $state): array
    {
        return $this->availableEventsWhere($state, fn(Event $event) => $event->getTimeout() !== null);
    }

    public function getCurrentState(): ?string
    {
        foreach ($this->getStates() as $state) {
            if ($state->isCurrent()) {
                return $state->getName();
            }
        }

        return null;
    }

    public function toJson(): string
    {
        $result = [
            'name' => $this->getName(),
            'states' => [],
            'transitions' => [],
            'events' => []
        ];

        // Process states
        foreach ($this->getStates() as $state) {
            $result['states'][] = [
                'name' => $state->getName(),
                'isCurrent' => $state->isCurrent()
            ];
        }

        // Process transitions
        foreach ($this->getTransitions() as $transition) {
            $result['transitions'][] = [
                'source' => $transition->getSource(),
                'target' => $transition->getTarget(),
                'event' => $transition->getEvent(),
                'condition' => $transition->getCondition(),
                'command' => $transition->getCommand()
            ];
        }

        // Process events
        foreach ($this->getEvents() as $event) {
            $result['events'][] = [
                'name' => $event->getName(),
                'command' => $event->getCommand(),
                'timeout' => $event->getTimeout() ?? null,
                'manual' => $event->isManual(),
                'onEnter' => $event->isOnEnter()
            ];
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param callable(Event): bool $filter
     *
     * @return Event[]
     */
    private function availableEventsWhere(string $state, callable $filter): array
    {
        $available = $this->getAvailableEvents($state);

        return array_values(array_filter(
            $this->events,
            fn(Event $event) => in_array($event->getName(), $available, true) && $filter($event)
        ));
    }
}
