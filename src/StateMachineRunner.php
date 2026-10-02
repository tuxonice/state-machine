<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tlab\StateMachine\Events\AfterTransition;
use Tlab\StateMachine\Events\BeforeTransition;
use Tlab\StateMachine\Events\NullEventDispatcher;
use Tlab\StateMachine\Events\TransitionBlocked;
use Tlab\StateMachine\Exceptions\OnEnterLoopException;
use Tlab\StateMachine\Exceptions\UnknownEventException;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\Flowchart\Designer;
use Tlab\StateMachine\Models\Event;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Models\Transition;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Resolver\CommandResolver;
use Tlab\StateMachine\Resolver\CommandResolverInterface;
use Tlab\StateMachine\Resolver\ConditionResolver;
use Tlab\StateMachine\Resolver\ConditionResolverInterface;
use Tlab\StateMachine\Scheduler\NullTimeoutScheduler;
use Tlab\StateMachine\Scheduler\TimeoutParser;
use Tlab\StateMachine\Scheduler\TimeoutScheduler;
use Tlab\StateMachine\Storage\StatefulStateStorage;
use Tlab\StateMachine\Storage\StateStorageInterface;

/**
 * Runs events against a state machine definition.
 *
 * Every collaborator is optional. By default conditions and commands are instantiated without
 * arguments, no events are dispatched, and subjects keep their own state (StatefulInterface).
 */
class StateMachineRunner
{
    /**
     * Most onEnter transitions chained after one event, to stop cycles in a definition.
     */
    public const MAX_ON_ENTER_TRANSITIONS = 50;

    private ConditionResolverInterface $conditionResolver;

    private CommandResolverInterface $commandResolver;

    private EventDispatcherInterface $dispatcher;

    private StateStorageInterface $storage;

    private TimeoutScheduler $scheduler;

    public function __construct(
        private StateMachine $stateMachine,
        ?ConditionResolverInterface $conditionResolver = null,
        ?CommandResolverInterface $commandResolver = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?StateStorageInterface $storage = null,
        ?TimeoutScheduler $scheduler = null,
    ) {
        $this->conditionResolver = $conditionResolver ?? new ConditionResolver();
        $this->commandResolver = $commandResolver ?? new CommandResolver();
        $this->dispatcher = $dispatcher ?? new NullEventDispatcher();
        $this->storage = $storage ?? new StatefulStateStorage();
        $this->scheduler = $scheduler ?? new NullTimeoutScheduler();
    }

    /**
     * @throws \Tlab\StateMachine\Exceptions\ValidationException
     */
    public static function fromJson(
        string $jsonDefinition,
        ?ConditionResolverInterface $conditionResolver = null,
        ?CommandResolverInterface $commandResolver = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?StateStorageInterface $storage = null,
        ?TimeoutScheduler $scheduler = null,
    ): self {
        return new self(
            (new DefinitionReader())->read($jsonDefinition),
            $conditionResolver,
            $commandResolver,
            $dispatcher,
            $storage,
            $scheduler,
        );
    }

    /**
     * Applies an event to a state and returns the resulting state.
     *
     * Returns the current state unchanged when the event is not available in it or
     * no condition passes. Use apply() to tell those cases apart.
     *
     * @param string $currentState
     * @param string $event
     * @param array<mixed> $data
     *
     * @return string
     * @throws UnknownStateException
     * @throws UnknownEventException
     */
    public function run(string $currentState, string $event, array $data = []): string
    {
        return $this->apply($currentState, $event, $data)->getState();
    }

    /**
     * Applies an event to a state.
     *
     * Transitions sharing the same source and event are tried in definition order: the
     * first whose condition passes wins, so an unconditional one acts as the "else" branch.
     * The event command runs only when a transition is taken.
     *
     * @param string $currentState
     * @param string $event
     * @param array<mixed> $data
     *
     * @return TransitionResult
     * @throws UnknownStateException
     * @throws UnknownEventException
     */
    public function apply(string $currentState, string $event, array $data = []): TransitionResult
    {
        return $this->transition($currentState, $event, $data, null);
    }

    /**
     * Applies an event to a subject, reading its state from and writing it back to the storage.
     *
     * A subject without a state starts from the initial state (the one flagged isCurrent).
     *
     * @param object $subject
     * @param string $event
     * @param array<mixed> $data
     *
     * @return TransitionResult
     * @throws UnknownStateException
     * @throws UnknownEventException
     */
    public function applyTo(object $subject, string $event, array $data = []): TransitionResult
    {
        return $this->transition($this->stateOf($subject), $event, $data, $subject);
    }

    /**
     * Whether applying the event to the state would move, evaluating conditions but causing no side effects.
     *
     * @param string $currentState
     * @param string $event
     * @param array<mixed> $data
     *
     * @throws UnknownStateException
     * @throws UnknownEventException
     */
    public function can(string $currentState, string $event, array $data = []): bool
    {
        $this->assertStateExists($currentState);
        $this->assertEventExists($event);

        return $this->selectTransition($currentState, $event, $data) !== null;
    }

    /**
     * @param object $subject
     * @param string $event
     * @param array<mixed> $data
     *
     * @throws UnknownStateException
     * @throws UnknownEventException
     */
    public function canApplyTo(object $subject, string $event, array $data = []): bool
    {
        return $this->can($this->stateOf($subject), $event, $data);
    }

    /**
     * Events that have a transition leaving the state. Conditions are not evaluated, use can() for that.
     *
     * @return string[]
     * @throws UnknownStateException
     */
    public function availableEvents(string $state): array
    {
        $this->assertStateExists($state);

        return $this->stateMachine->getAvailableEvents($state);
    }

    /**
     * @return string[]
     * @throws UnknownStateException
     */
    public function availableEventsFor(object $subject): array
    {
        return $this->availableEvents($this->stateOf($subject));
    }

    /**
     * Events that need a person to trigger them: manual events with a transition leaving the state.
     * Conditions are not evaluated, use can() for that.
     *
     * @return string[]
     * @throws UnknownStateException
     */
    public function availableManualEvents(string $state): array
    {
        $this->assertStateExists($state);

        return array_map(
            fn(Event $event) => $event->getName(),
            $this->stateMachine->getManualEvents($state)
        );
    }

    /**
     * @return string[]
     * @throws UnknownStateException
     */
    public function availableManualEventsFor(object $subject): array
    {
        return $this->availableManualEvents($this->stateOf($subject));
    }

    /**
     * Schedules the timeout events of the subject's current state, replacing what was scheduled before.
     *
     * This happens by itself whenever applyTo() moves the subject. Call it for subjects that have not
     * moved yet, e.g. right after creating them in their initial state.
     *
     * @throws UnknownStateException
     */
    public function scheduleTimeouts(object $subject, ?DateTimeImmutable $now = null): void
    {
        $state = $this->stateOf($subject);
        $this->assertStateExists($state);
        $now ??= new DateTimeImmutable();

        $this->scheduler->cancel($subject);
        foreach ($this->stateMachine->getTimeoutEvents($state) as $event) {
            $this->scheduler->schedule(
                $subject,
                $state,
                $event->getName(),
                TimeoutParser::dueAt($now, (string) $event->getTimeout())
            );
        }
    }

    /**
     * Applies every timeout event that is due. Call it from cron or a queue worker.
     *
     * A timeout is skipped when its subject is no longer in the state it was scheduled for. One whose
     * conditions do not pass is dropped, it is not retried.
     *
     * @return TransitionResult[] One result per timeout applied
     */
    public function processTimeouts(?DateTimeImmutable $now = null): array
    {
        $results = [];
        foreach ($this->scheduler->pullDue($now ?? new DateTimeImmutable()) as $timeout) {
            $subject = $timeout->getSubject();
            if ($this->storage->read($subject) !== $timeout->getState()) {
                continue;
            }

            $results[] = $this->applyTo($subject, $timeout->getEvent());
        }

        return $results;
    }

    public function getStateMachine(): StateMachine
    {
        return $this->stateMachine;
    }

    /**
     * Generates a Mermaid HTML diagram of the state machine
     *
     * @return string HTML representation of the state machine diagram
     */
    public function generateHtmlDiagram(): string
    {
        $designer = new Designer();

        return $designer->renderGraph($this->stateMachine->toJson());
    }

    /**
     * Generates a Mermaid Markdown diagram of the state machine
     *
     * @return string Markdown representation of the state machine diagram
     */
    public function generateMarkdownDiagram(): string
    {
        $designer = new Designer();
        return $designer->renderMarkdown($this->stateMachine->toJson());
    }

    /**
     * @param array<mixed> $data
     */
    private function transition(string $currentState, string $event, array $data, ?object $subject): TransitionResult
    {
        $this->assertStateExists($currentState);
        $this->assertEventExists($event);

        if ($this->stateMachine->getTransitionsFor($currentState, $event) === []) {
            return new TransitionResult(TransitionStatus::NoTransition, $currentState);
        }

        $transition = $this->selectTransition($currentState, $event, $data);
        if ($transition === null) {
            $name = $this->stateMachine->getName();
            $this->dispatcher->dispatch(new TransitionBlocked($name, $currentState, $event, $data, $subject));

            return new TransitionResult(TransitionStatus::Blocked, $currentState);
        }

        $state = $this->move($transition, $currentState, $event, $data, $subject);
        $events = [$event];

        // Events flagged onEnter fire by themselves as soon as their state is entered.
        while (($auto = $this->selectOnEnter($state, $data)) !== null) {
            if (count($events) > self::MAX_ON_ENTER_TRANSITIONS) {
                throw OnEnterLoopException::afterTransitions(self::MAX_ON_ENTER_TRANSITIONS, $state);
            }

            [$autoEvent, $autoTransition] = $auto;
            $state = $this->move($autoTransition, $state, $autoEvent, $data, $subject);
            $events[] = $autoEvent;
        }

        if ($subject !== null) {
            $this->scheduleTimeouts($subject);
        }

        return new TransitionResult(TransitionStatus::Moved, $state, $events);
    }

    /**
     * Takes a transition: runs the commands, stores the new state and dispatches the events around it.
     *
     * @param array<mixed> $data
     *
     * @return string The target state
     */
    private function move(
        Transition $transition,
        string $source,
        string $event,
        array $data,
        ?object $subject,
    ): string {
        $name = $this->stateMachine->getName();
        $target = $transition->getTarget();
        $this->dispatcher->dispatch(new BeforeTransition($name, $source, $event, $target, $data, $subject));

        $this->runCommand($this->stateMachine->getEvent($event)?->getCommand(), $data);
        $this->runCommand($transition->getCommand(), $data);
        if ($subject !== null) {
            $this->storage->write($subject, $target);
        }

        $this->dispatcher->dispatch(new AfterTransition($name, $source, $event, $target, $data, $subject));

        return $target;
    }

    /**
     * The first onEnter event of the state with a transition whose condition passes.
     *
     * @param array<mixed> $data
     *
     * @return array{string, Transition}|null
     */
    private function selectOnEnter(string $state, array $data): ?array
    {
        foreach ($this->stateMachine->getOnEnterEvents($state) as $event) {
            $transition = $this->selectTransition($state, $event->getName(), $data);
            if ($transition !== null) {
                return [$event->getName(), $transition];
            }
        }

        return null;
    }

    /**
     * The first transition for the state and event whose condition passes.
     *
     * @param array<mixed> $data
     */
    private function selectTransition(string $currentState, string $event, array $data): ?Transition
    {
        foreach ($this->stateMachine->getTransitionsFor($currentState, $event) as $transition) {
            $condition = $transition->getCondition();
            if ($condition === null || $this->conditionResolver->resolve($condition)->check($data)) {
                return $transition;
            }
        }

        return null;
    }

    private function stateOf(object $subject): string
    {
        $state = $this->storage->read($subject) ?? $this->stateMachine->getCurrentState();
        if ($state === null) {
            throw UnknownStateException::noInitialState();
        }

        return $state;
    }

    private function assertStateExists(string $state): void
    {
        if (!$this->stateMachine->hasState($state)) {
            throw UnknownStateException::forState($state);
        }
    }

    private function assertEventExists(string $event): void
    {
        if (!$this->stateMachine->hasEvent($event)) {
            throw UnknownEventException::forEvent($event);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function runCommand(?string $command, array $data): void
    {
        if ($command !== null) {
            $this->commandResolver->resolve($command)->run($data);
        }
    }
}
