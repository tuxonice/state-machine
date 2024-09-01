<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Models;

class Event
{
    private string $name;

    private ?string $command;

    /**
     * @param array<string,mixed> $eventData
     *
     * @return self
     */
    public static function createFromArray(array $eventData): self
    {
        return new self($eventData);
    }

    /**
     * @param array<string,mixed> $eventData
     */
    private function __construct(array $eventData)
    {
        $this->name = $eventData['name'];
        $this->command = $eventData['command'];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }
}
