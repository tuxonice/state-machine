<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Validator;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;
use Tlab\StateMachine\Exceptions\ValidationException;

class StateMachineValidator
{
    /**
     * @param string $jsonDefinition
     * @param array<mixed> $errors
     *
     * @return bool
     */
    public function validateSchema(string $jsonDefinition, array &$errors): bool
    {
        $schema = file_get_contents(__DIR__ . '/Schema/schema.json');

        $data = json_decode($jsonDefinition);

        $validator = new Validator();
        $validator->setMaxErrors(5);

        $result = $validator->validate($data, $schema);

        if ($result->isValid()) {
            if (!$this->validateTransitions($data)) {
                return false;
            }
            return true;
        }

        $error = $result->error();
        $formatter = new ErrorFormatter();

        $errors = $formatter->format($error, false);

        return false;
    }

    /**
     * @throws ValidationException
     */
    private function validateTransitions(stdClass $data): bool
    {
        //State list
        $stateList = array_map(fn(stdClass $state) => $state->name, $data->states);

        //Event list
        $eventList = array_map(fn(stdClass $event) => $event->name, $data->events);

        /** @var stdClass $transition */
        foreach ($data->transitions as $transition) {
            if (!in_array($transition->source, $stateList)) {
                throw new ValidationException("Transition source '{$transition->source}' does not exist in states list");
            }

            if (!in_array($transition->target, $stateList)) {
                throw new ValidationException("Transition target '{$transition->target}' does not exist in states list");
            }

            if (!in_array($transition->event, $eventList)) {
                throw new ValidationException("Transition event '{$transition->event}' does not exist in events list");
            }
        }

        return true;
    }
}
