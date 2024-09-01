<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Validator;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;

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

    private function validateTransitions(stdClass $data): bool
    {
        //State list
        $stateList = array_map(fn(stdClass $state) => $state->name, $data->states);

        //Event list
        $eventList = array_map(fn(stdClass $event) => $event->name, $data->events);

        /** @var stdClass $transition */
        foreach ($data->transitions as $transition) {
            if (!in_array($transition->from, $stateList) || !in_array($transition->to, $stateList)) {
                return false;
            }

            if (!in_array($transition->event, $eventList)) {
                return false;
            }
        }

        return true;
    }
}
