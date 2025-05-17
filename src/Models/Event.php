<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Models;

class Event
{
    private string $name;

    private ?string $command;

    private ?string $timeout;

    private bool $manual =  false;

    private bool $onEnter = false;

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
        $this->timeout = $eventData['timeout'] ?? null;
        $this->onEnter = $eventData['onEnter'] ?? false;
        $this->manual = $eventData['manual'] ?? false;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTimeout(): ?string
    {
        return $this->timeout;
    }

    public function isOnEnter(): bool
    {
        return $this->onEnter;
    }

    public function isManual(): bool
    {
        return $this->manual;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }
}
