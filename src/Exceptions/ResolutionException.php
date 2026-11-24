<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class ResolutionException extends StateMachineException
{
    /**
     * @param array<string, string> $errors Reason per class, when several classes were checked at once
     */
    public function __construct(string $message = '', private array $errors = [])
    {
        parent::__construct($message);
    }

    /**
     * @param array<string, string> $errors Reason per class
     */
    public static function forDefinition(string $machine, array $errors): self
    {
        $count = count($errors);

        return new self(
            sprintf("%d %s in state machine '%s' cannot be resolved:\n- ", $count, $count === 1 ? 'class' : 'classes', $machine)
            . implode("\n- ", $errors),
            $errors
        );
    }

    /**
     * The reason for each class that cannot be resolved, keyed by class name. Empty for a single class.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function classNotFound(string $class): self
    {
        return new self("Class '{$class}' does not exist");
    }

    public static function wrongType(string $class, string $interface): self
    {
        return new self("Class '{$class}' must implement {$interface}");
    }

    public static function needsConstructorArguments(string $class): self
    {
        return new self("Class '{$class}' needs constructor arguments; register it in the container");
    }
}
