<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class ValidationException extends StateMachineException
{
    /**
     * @param string $message
     * @param array<mixed> $errors Schema validation errors, keyed by JSON pointer
     */
    public function __construct(string $message = '', private array $errors = [])
    {
        parent::__construct($message);
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
