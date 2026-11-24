<?php

namespace Tlab\Tests\Support;

use DateTimeImmutable;
use Tlab\StateMachine\Scheduler\ScheduledTimeout;
use Tlab\StateMachine\Scheduler\TimeoutScheduler;

/**
 * A scheduler that only implements the original interface, like the ones written before acknowledging existed.
 */
class PullOnlyTimeoutScheduler implements TimeoutScheduler
{
    /** @var ScheduledTimeout[] */
    public array $timeouts = [];

    public function schedule(object $subject, string $state, string $event, DateTimeImmutable $dueAt): void
    {
        $this->timeouts[] = new ScheduledTimeout($subject, $state, $event, $dueAt);
    }

    public function cancel(object $subject): void
    {
        $this->timeouts = array_values(array_filter($this->timeouts, fn($t) => $t->getSubject() !== $subject));
    }

    public function pullDue(DateTimeImmutable $now): array
    {
        $due = array_values(array_filter($this->timeouts, fn($t) => $t->getDueAt() <= $now));
        $this->timeouts = array_values(array_filter($this->timeouts, fn($t) => $t->getDueAt() > $now));

        return $due;
    }
}
