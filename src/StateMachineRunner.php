<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

use Tlab\StateMachine\Commands\CommandInterface;
use Tlab\StateMachine\Exceptions\UnknownEventException;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\Flowchart\Designer;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Models\Transition;

class StateMachineRunner
{
    private StateMachine $stateMachine;

    public function __construct(private string $jsonDefinition)
    {
        $definitionReader = new DefinitionReader();
        $this->stateMachine = $definitionReader->read($this->jsonDefinition);
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
        $this->assertStateExists($currentState);
        $this->assertEventExists($event);

        $candidates = $this->getTransitions($currentState, $event);
        if ($candidates === []) {
            return new TransitionResult(TransitionStatus::NoTransition, $currentState);
        }

        foreach ($candidates as $transition) {
            if ($transition->checkCondition($data)) {
                $this->runEventCommand($event, $data);

                return new TransitionResult(TransitionStatus::Moved, $transition->getTarget());
            }
        }

        return new TransitionResult(TransitionStatus::Blocked, $currentState);
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
     * @return Transition[]
     */
    private function getTransitions(string $currentState, string $event): array
    {
        return array_values(array_filter(
            $this->stateMachine->getTransitions(),
            fn(Transition $transition) => $transition->getSource() === $currentState
                && $transition->getEvent() === $event
        ));
    }

    private function assertStateExists(string $state): void
    {
        foreach ($this->stateMachine->getStates() as $machineState) {
            if ($machineState->getName() === $state) {
                return;
            }
        }

        throw UnknownStateException::forState($state);
    }

    private function assertEventExists(string $event): void
    {
        foreach ($this->stateMachine->getEvents() as $machineEvent) {
            if ($machineEvent->getName() === $event) {
                return;
            }
        }

        throw UnknownEventException::forEvent($event);
    }

    /**
     * @param string $event
     * @param array<mixed> $data
     *
     * @return void
     */
    private function runEventCommand(string $event, array $data): void
    {
        foreach ($this->stateMachine->getEvents() as $machineEvent) {
            if ($machineEvent->getName() === $event) {
                if ($machineEvent->getCommand()) {
                    /** @var CommandInterface $command */
                    $command = new ($machineEvent->getCommand());
                    $command->run($data);
                }
            }
        }
    }
}
