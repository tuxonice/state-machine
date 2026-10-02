<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use DateTimeImmutable;

/**
 * An event waiting to be applied to a subject that sits in a state.
 */
class ScheduledTimeout
{
    public function __construct(
        private object $subject,
        private string $state,
        private string $event,
        private DateTimeImmutable $dueAt,
    ) {
    }

    public function getSubject(): object
    {
        return $this->subject;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getDueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }
}
