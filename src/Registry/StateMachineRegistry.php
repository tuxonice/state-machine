<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Registry;

use Tlab\StateMachine\Exceptions\MachineRegistryException;
use Tlab\StateMachine\StateMachineRunner;

/**
 * Holds several runners and finds them by name, so an application can drive many machines
 * (order, payment, shipment...) from one place.
 */
class StateMachineRegistry
{
    /** @var array<string,StateMachineRunner> */
    private array $runners = [];

    /**
     * Registers a runner under the name given, or under the name of its definition.
     *
     * @throws MachineRegistryException when the name is already taken
     */
    public function register(StateMachineRunner $runner, ?string $name = null): self
    {
        $name ??= $runner->getStateMachine()->getName();
        if ($this->has($name)) {
            throw MachineRegistryException::alreadyRegistered($name);
        }

        $this->runners[$name] = $runner;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->runners[$name]);
    }

    /**
     * @throws MachineRegistryException when no machine has that name
     */
    public function get(string $name): StateMachineRunner
    {
        return $this->runners[$name] ?? throw MachineRegistryException::unknown($name);
    }

    /**
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->runners);
    }
}
