<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

use Tlab\StateMachine\Commands\CommandInterface;
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
    public function run(string $currentState, string $event, array $data): ?string
    {
        $transition = $this->getTransition($currentState, $event);
        if ($transition === null || !$transition->checkCondition($data)) {
            return $currentState;
        }

        $this->runEventCommand($event, $data);

        return $transition->getTo();
    }


    private function getTransition(string $currentState, string $event): ?Transition
    {
        foreach ($this->stateMachine->getTransitions() as $transition) {
            if ($transition->getFrom() === $currentState && $transition->getEvent() === $event) {
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
