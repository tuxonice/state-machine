<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tlab\StateMachine\Events\AfterTransition;
use Tlab\StateMachine\Events\BeforeTransition;
use Tlab\StateMachine\Events\NullEventDispatcher;
use Tlab\StateMachine\Events\TransitionBlocked;
use Tlab\StateMachine\Exceptions\UnknownEventException;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\Flowchart\Designer;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Models\Transition;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Resolver\CommandResolver;
use Tlab\StateMachine\Resolver\CommandResolverInterface;
use Tlab\StateMachine\Resolver\ConditionResolver;
use Tlab\StateMachine\Resolver\ConditionResolverInterface;
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
    private ConditionResolverInterface $conditionResolver;

    private CommandResolverInterface $commandResolver;

    private EventDispatcherInterface $dispatcher;

    private StateStorageInterface $storage;

    public function __construct(
        private StateMachine $stateMachine,
        ?ConditionResolverInterface $conditionResolver = null,
        ?CommandResolverInterface $commandResolver = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?StateStorageInterface $storage = null,
    ) {
        $this->conditionResolver = $conditionResolver ?? new ConditionResolver();
        $this->commandResolver = $commandResolver ?? new CommandResolver();
        $this->dispatcher = $dispatcher ?? new NullEventDispatcher();
        $this->storage = $storage ?? new StatefulStateStorage();
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
    ): self {
        return new self(
            (new DefinitionReader())->read($jsonDefinition),
            $conditionResolver,
            $commandResolver,
            $dispatcher,
            $storage,
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

        $name = $this->stateMachine->getName();

        if ($this->stateMachine->getTransitionsFor($currentState, $event) === []) {
            return new TransitionResult(TransitionStatus::NoTransition, $currentState);
        }

        $transition = $this->selectTransition($currentState, $event, $data);
        if ($transition === null) {
            $this->dispatcher->dispatch(new TransitionBlocked($name, $currentState, $event, $data, $subject));

            return new TransitionResult(TransitionStatus::Blocked, $currentState);
        }

        $target = $transition->getTarget();
        $this->dispatcher->dispatch(new BeforeTransition($name, $currentState, $event, $target, $data, $subject));

        $this->runEventCommand($event, $data);
        if ($subject !== null) {
            $this->storage->write($subject, $target);
        }

        $this->dispatcher->dispatch(new AfterTransition($name, $currentState, $event, $target, $data, $subject));

        return new TransitionResult(TransitionStatus::Moved, $target);
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
     * @param string $event
     * @param array<mixed> $data
     *
     * @return void
     */
    private function runEventCommand(string $event, array $data): void
    {
        $command = $this->stateMachine->getEvent($event)?->getCommand();
        if ($command !== null) {
            $this->commandResolver->resolve($command)->run($data);
        }
    }
}
