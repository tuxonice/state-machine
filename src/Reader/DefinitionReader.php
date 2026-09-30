<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Reader;

use Tlab\StateMachine\Exceptions\ValidationException;
use Tlab\StateMachine\Models\Event;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Models\State;
use Tlab\StateMachine\Models\Transition;
use Tlab\StateMachine\Validator\StateMachineValidator;

class DefinitionReader
{
    private StateMachineValidator $validator;

    public function __construct()
    {
        $this->validator = new StateMachineValidator();
    }

    /**
     * @param string $jsonDefinition
     *
     * @return \Tlab\StateMachine\Models\StateMachine
     * @throws \Tlab\StateMachine\Exceptions\ValidationException
     *
     */
    public function read(string $jsonDefinition): StateMachine
    {
        $errors = [];

        if (!$this->validator->validateSchema($jsonDefinition, $errors)) {
            $details = implode('; ', array_map(
                fn($pointer, $message) => "{$pointer}: {$message}",
                array_keys($errors),
                $errors
            ));

            throw new ValidationException("Invalid state machine definition - {$details}", $errors);
        }

        $definitionData = json_decode($jsonDefinition, true);

        $stateMachine = new StateMachine();
        $stateMachine->setName($definitionData['name']);
        $this->setStates($stateMachine, $definitionData);
        $this->setTransitions($stateMachine, $definitionData);
        $this->setEvents($stateMachine, $definitionData);

        return $stateMachine;
    }

    /**
     * @param StateMachine $stateMachine
     * @param array<string,string|array<mixed>> $definitionData
     *
     * @return void
     */
    private function setStates(StateMachine $stateMachine, array $definitionData): void
    {
        foreach ($definitionData['states'] as $stateData) {
            $stateMachine->addState(
                (new State($stateData['name']))->setIsCurrent($stateData['isCurrent'] ?? false)
            );
        }
    }

    private function setTransitions(StateMachine $stateMachine, mixed $definitionData): void
    {
        foreach ($definitionData['transitions'] as $transitionData) {
            $transition = Transition::createFromArray($transitionData);
            $stateMachine->addTransition($transition);
        }
    }

    private function setEvents(StateMachine $stateMachine, mixed $definitionData): void
    {
        foreach ($definitionData['events'] as $eventData) {
            $event = Event::createFromArray($eventData);
            $stateMachine->addEvent($event);
        }
    }
}
