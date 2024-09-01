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
    private StateMachine $stateMachine;

    private StateMachineValidator $validator;

    public function __construct()
    {
        $this->stateMachine = new StateMachine();
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
            throw new ValidationException('Invalid definition file: ' . implode("\n", $errors));
        }

        $definitionData = json_decode($jsonDefinition, true);

        $flowName = $definitionData['name'];
        $this->stateMachine->setName($flowName);
        $this->setStates($this->stateMachine, $definitionData);
        $this->setTransitions($this->stateMachine, $definitionData);
        $this->setEvents($this->stateMachine, $definitionData);

        return $this->stateMachine;
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
            $stateMachine->addState(new State($stateData['name']));
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
