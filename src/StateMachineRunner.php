<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SplObjectStorage;
use Tlab\StateMachine\Clock\SystemClock;
use Tlab\StateMachine\Events\AfterTransition;
use Tlab\StateMachine\Events\BeforeTransition;
use Tlab\StateMachine\Events\NullEventDispatcher;
use Tlab\StateMachine\Events\TransitionBlocked;
use Throwable;
use Tlab\StateMachine\Exceptions\OnEnterLoopException;
use Tlab\StateMachine\Exceptions\ResolutionException;
use Tlab\StateMachine\Exceptions\TimeoutProcessingException;
use Tlab\StateMachine\Exceptions\UnknownEventException;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\Exceptions\GraphRenderException;
use Tlab\StateMachine\Flowchart\DiagramRenderer;
use Tlab\StateMachine\Flowchart\Designer;
use Tlab\StateMachine\Models\Event;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Models\Transition;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Resolver\CommandResolver;
use Tlab\StateMachine\Resolver\CommandResolverInterface;
use Tlab\StateMachine\Resolver\ConditionResolver;
use Tlab\StateMachine\Resolver\ConditionResolverInterface;
use Tlab\StateMachine\Scheduler\AcknowledgingTimeoutScheduler;
use Tlab\StateMachine\Scheduler\FailedTimeout;
use Tlab\StateMachine\Scheduler\NullTimeoutScheduler;
use Tlab\StateMachine\Scheduler\ScheduledTimeout;
use Tlab\StateMachine\Scheduler\TimeoutParser;
use Tlab\StateMachine\Scheduler\TimeoutScheduler;
use Tlab\StateMachine\Storage\StatefulStateStorage;
use Tlab\StateMachine\Storage\StateStorageInterface;

/**
 * Runs events against a state machine definition.
 *
 * Every collaborator is optional. By default conditions and commands are instantiated without
 * arguments, no events are dispatched, subjects keep their own state (StatefulInterface) and timeouts
 * follow the system clock.
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

    private ClockInterface $clock;

    public function __construct(
        private StateMachine $stateMachine,
        ?ConditionResolverInterface $conditionResolver = null,
        ?CommandResolverInterface $commandResolver = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?StateStorageInterface $storage = null,
        ?TimeoutScheduler $scheduler = null,
        ?ClockInterface $clock = null,
    ) {
        $this->conditionResolver = $conditionResolver ?? new ConditionResolver();
        $this->commandResolver = $commandResolver ?? new CommandResolver();
        $this->dispatcher = $dispatcher ?? new NullEventDispatcher();
        $this->storage = $storage ?? new StatefulStateStorage();
        $this->scheduler = $scheduler ?? new NullTimeoutScheduler();
        $this->clock = $clock ?? new SystemClock();
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
        ?ClockInterface $clock = null,
    ): self {
        return new self(
            (new DefinitionReader())->read($jsonDefinition),
            $conditionResolver,
            $commandResolver,
            $dispatcher,
            $storage,
            $scheduler,
            $clock,
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

        return $this->selectTransition($currentState, $event, $data, null) !== null;
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
        $state = $this->stateOf($subject);
        $this->assertStateExists($state);
        $this->assertEventExists($event);

        return $this->selectTransition($state, $event, $data, $subject) !== null;
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
     * Events that can be applied in the state right now: those with a transition whose condition passes (or
     * that has none). Unlike availableEvents(), conditions are evaluated, with the given data and no subject.
     *
     * @param array<mixed> $data
     *
     * @return string[]
     * @throws UnknownStateException
     */
    public function allowedEvents(string $state, array $data = []): array
    {
        return $this->allowedAmong($state, $this->availableEvents($state), $data, null);
    }

    /**
     * Like allowedEvents(), for the subject's state, with the subject passed to the conditions.
     *
     * @param array<mixed> $data
     *
     * @return string[]
     * @throws UnknownStateException
     */
    public function allowedEventsFor(object $subject, array $data = []): array
    {
        $state = $this->stateOf($subject);

        return $this->allowedAmong($state, $this->availableEvents($state), $data, $subject);
    }

    /**
     * The manual events that can be applied in the state right now, which is what a UI would offer as buttons.
     *
     * @param array<mixed> $data
     *
     * @return string[]
     * @throws UnknownStateException
     */
    public function allowedManualEvents(string $state, array $data = []): array
    {
        return $this->allowedAmong($state, $this->availableManualEvents($state), $data, null);
    }

    /**
     * Like allowedManualEvents(), for the subject's state, with the subject passed to the conditions.
     *
     * @param array<mixed> $data
     *
     * @return string[]
     * @throws UnknownStateException
     */
    public function allowedManualEventsFor(object $subject, array $data = []): array
    {
        $state = $this->stateOf($subject);

        return $this->allowedAmong($state, $this->availableManualEvents($state), $data, $subject);
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
        $now ??= $this->clock->now();

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
     * An AcknowledgingTimeoutScheduler may hand out due timeouts in batches; this keeps claiming until nothing
     * new is due, so one call works through a backlog. It stops after $maxBatches claims, and when a claim
     * only returns timeouts this call already tried (a failed one is retried on a later call, not this one).
     *
     * @param int $maxBatches the most claims (or pulls) one call makes
     *
     * @return TransitionResult[] One result per timeout applied
     */
    public function processTimeouts(?DateTimeImmutable $now = null, int $maxBatches = 100): array
    {
        if ($maxBatches < 1) {
            throw new InvalidArgumentException('maxBatches must be at least 1');
        }

        $now ??= $this->clock->now();
        $scheduler = $this->scheduler;
        $acknowledging = $scheduler instanceof AcknowledgingTimeoutScheduler;

        $results = [];
        $failures = [];
        /** @var SplObjectStorage<ScheduledTimeout, null> $tried */
        $tried = new SplObjectStorage();
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $due = $acknowledging ? $scheduler->claimDue($now) : $scheduler->pullDue($now);
            $new = array_filter($due, fn(ScheduledTimeout $timeout) => !$tried->contains($timeout));
            if ($new === []) {
                break;
            }

            foreach ($new as $timeout) {
                $tried->attach($timeout);
                try {
                    $subject = $timeout->getSubject();
                    if ($this->storage->read($subject) === $timeout->getState()) {
                        $results[] = $this->applyTo($subject, $timeout->getEvent());
                    }

                    // Applied, blocked by its conditions or obsolete: either way it is done with.
                    if ($acknowledging) {
                        $scheduler->acknowledge($timeout);
                    }
                } catch (Throwable $error) {
                    $failures[] = new FailedTimeout($timeout, $error);
                }
            }
        }

        if ($failures !== []) {
            throw TimeoutProcessingException::withFailures($results, $failures);
        }

        return $results;
    }

    /**
     * Resolves every condition and command the definition references, through the same resolvers (and
     * container) used at run time, so a missing class, a wrong type or a missing container entry shows up now
     * instead of when the transition is first taken. Conditions are not evaluated and commands are not run.
     *
     * Call it at boot or from a test in CI.
     *
     * @throws ResolutionException listing every class that cannot be resolved, see getErrors()
     */
    public function validateClasses(): void
    {
        $errors = [];
        $check = function (?string $class, callable $resolve) use (&$errors): void {
            if ($class === null || isset($errors[$class])) {
                return;
            }
            try {
                $resolve($class);
            } catch (ResolutionException $e) {
                $errors[$class] = $e->getMessage();
            }
        };

        foreach ($this->stateMachine->getTransitions() as $transition) {
            $check($transition->getCondition(), fn(string $class) => $this->conditionResolver->resolve($class));
            $check($transition->getCommand(), fn(string $class) => $this->commandResolver->resolve($class));
        }
        foreach ($this->stateMachine->getEvents() as $event) {
            $check($event->getCommand(), fn(string $class) => $this->commandResolver->resolve($class));
        }

        if ($errors !== []) {
            throw ResolutionException::forDefinition($this->stateMachine->getName(), $errors);
        }
    }

    public function getStateMachine(): StateMachine
    {
        return $this->stateMachine;
    }

    /**
     * Generates an HTML diagram of the state machine.
     *
     * Uses the Mermaid Designer, which needs the optional jbzoo/mermaid-php package, unless
     * another renderer is given.
     *
     * @throws GraphRenderException
     */
    public function generateHtmlDiagram(?DiagramRenderer $renderer = null): string
    {
        return ($renderer ?? new Designer())->renderHtml($this->stateMachine);
    }

    /**
     * Generates a Markdown diagram of the state machine, see generateHtmlDiagram().
     *
     * @throws GraphRenderException
     */
    public function generateMarkdownDiagram(?DiagramRenderer $renderer = null): string
    {
        return ($renderer ?? new Designer())->renderMarkdown($this->stateMachine);
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

        $transition = $this->selectTransition($currentState, $event, $data, $subject);
        if ($transition === null) {
            $name = $this->stateMachine->getName();
            $this->dispatcher->dispatch(new TransitionBlocked($name, $currentState, $event, $data, $subject));

            return new TransitionResult(TransitionStatus::Blocked, $currentState);
        }

        $state = $this->move($transition, $currentState, $event, $data, $subject);
        $events = [$event];

        // Events flagged onEnter fire by themselves as soon as their state is entered.
        while (($auto = $this->selectOnEnter($state, $data, $subject)) !== null) {
            if (count($events) > self::MAX_ON_ENTER_TRANSITIONS) {
                throw OnEnterLoopException::afterTransitions(self::MAX_ON_ENTER_TRANSITIONS, $state);
            }

            [$autoEvent, $autoTransition] = $auto;
            $state = $this->move($autoTransition, $state, $autoEvent, $data, $subject);
            $events[] = $autoEvent;
        }

        return new TransitionResult(TransitionStatus::Moved, $state, $events);
    }

    /**
     * Takes a transition: runs the commands, stores the new state, schedules its timeouts and dispatches
     * the events around it.
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

        $context = new TransitionContext($name, $source, $event, $target, $data, $subject);
        $this->runCommand($this->stateMachine->getEvent($event)?->getCommand(), $context);
        $this->runCommand($transition->getCommand(), $context);
        if ($subject !== null) {
            $this->storage->write($subject, $target);
            // Right after the write, so the stored state always has its own timeouts, even when a listener fails.
            // A self-transition flagged keepTimeouts leaves the state's running timeouts as they are.
            if (!$transition->keepsTimeouts()) {
                $this->scheduleTimeouts($subject);
            }
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
    private function selectOnEnter(string $state, array $data, ?object $subject): ?array
    {
        foreach ($this->stateMachine->getOnEnterEvents($state) as $event) {
            $transition = $this->selectTransition($state, $event->getName(), $data, $subject);
            if ($transition !== null) {
                return [$event->getName(), $transition];
            }
        }

        return null;
    }

    /**
     * The events of the list that have a transition from the state whose condition passes.
     *
     * @param string[] $events
     * @param array<mixed> $data
     *
     * @return string[]
     */
    private function allowedAmong(string $state, array $events, array $data, ?object $subject): array
    {
        return array_values(array_filter(
            $events,
            fn(string $event) => $this->selectTransition($state, $event, $data, $subject) !== null
        ));
    }

    /**
     * The first transition for the state and event whose condition passes.
     *
     * @param array<mixed> $data
     */
    private function selectTransition(string $currentState, string $event, array $data, ?object $subject): ?Transition
    {
        $name = $this->stateMachine->getName();
        foreach ($this->stateMachine->getTransitionsFor($currentState, $event) as $transition) {
            $condition = $transition->getCondition();
            if ($condition === null) {
                return $transition;
            }

            $context = new TransitionContext($name, $currentState, $event, $transition->getTarget(), $data, $subject);
            if ($this->conditionResolver->resolve($condition)->check($context)) {
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

    private function runCommand(?string $command, TransitionContext $context): void
    {
        if ($command !== null) {
            $this->commandResolver->resolve($command)->run($context);
        }
    }
}
