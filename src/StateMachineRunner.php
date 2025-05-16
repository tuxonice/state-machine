<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

use Tlab\StateMachine\Commands\CommandInterface;
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
     * @param string $currentState
     * @param string $event
     * @param array<mixed> $data
     *
     * @return string|null
     */
    public function run(string $currentState, string $event, array $data = []): ?string
    {
        $transition = $this->getTransition($currentState, $event);
        if ($transition === null || !$transition->checkCondition($data)) {
            return $currentState;
        }

        $this->runEventCommand($event, $data);

        return $transition->getTarget();
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


    private function getTransition(string $currentState, string $event): ?Transition
    {
        foreach ($this->stateMachine->getTransitions() as $transition) {
            if ($transition->getSource() === $currentState && $transition->getEvent() === $event) {
                return $transition;
            }
        }

        return null;
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
