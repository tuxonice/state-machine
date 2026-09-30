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

        return new StateMachine(
            $definitionData['name'],
            array_map(
                fn(array $state) => new State($state['name'], $state['isCurrent'] ?? false),
                $definitionData['states']
            ),
            array_map(fn(array $data) => Transition::createFromArray($data), $definitionData['transitions']),
            array_map(fn(array $data) => Event::createFromArray($data), $definitionData['events']),
        );
    }

    /**
     * @param array<string,mixed> $definition
     *
     * @return StateMachine
     * @throws ValidationException
     */
    public function readArray(array $definition): StateMachine
    {
        return $this->read(json_encode($definition, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws ValidationException
     */
    public function readFile(string $path): StateMachine
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new ValidationException("Definition file '{$path}' cannot be read");
        }

        return $this->read($contents);
    }
}
