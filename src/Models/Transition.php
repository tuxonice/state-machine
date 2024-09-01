<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Models;

use Tlab\StateMachine\Conditions\ConditionInterface;

class Transition
{
    private string $from;

    private string $to;

    private string $event;

    private ?string $condition;


    /**
     * @param array<string,mixed> $transitionData
     *
     * @return self
     */
    public static function createFromArray(array $transitionData): self
    {
        return new self($transitionData);
    }

    /**
     * @param array<string,mixed> $transitionData
     */
    private function __construct(array $transitionData)
    {
        $this->from = $transitionData['from'];
        $this->to = $transitionData['to'];
        $this->event = $transitionData['event'];
        $this->condition = $transitionData['condition'];
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    /**
     * @param array<mixed> $data
     *
     * @return bool
     */
    public function checkCondition(array $data): bool
    {
        if ($this->condition === null) {
            return true;
        }

        /** @var ConditionInterface $condition */
        $condition = (new $this->condition());

        return $condition->check($data);
    }
}
