<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use DateTimeImmutable;

/**
 * Keeps timeouts in memory. Useful for tests and long-running processes.
 */
class InMemoryTimeoutScheduler implements TimeoutScheduler
{
    /** @var ScheduledTimeout[] */
    private array $timeouts = [];

    public function schedule(object $subject, string $state, string $event, DateTimeImmutable $dueAt): void
    {
        $this->timeouts[] = new ScheduledTimeout($subject, $state, $event, $dueAt);
    }

    public function cancel(object $subject): void
    {
        $this->timeouts = array_values(array_filter(
            $this->timeouts,
            fn(ScheduledTimeout $timeout) => $timeout->getSubject() !== $subject
        ));
    }

    public function pullDue(DateTimeImmutable $now): array
    {
        $due = array_values(array_filter(
            $this->timeouts,
            fn(ScheduledTimeout $timeout) => $timeout->getDueAt() <= $now
        ));
        $this->timeouts = array_values(array_filter(
            $this->timeouts,
            fn(ScheduledTimeout $timeout) => $timeout->getDueAt() > $now
        ));

        usort($due, fn(ScheduledTimeout $a, ScheduledTimeout $b) => $a->getDueAt() <=> $b->getDueAt());

        return $due;
    }

    /**
     * @return ScheduledTimeout[]
     */
    public function all(): array
    {
        return $this->timeouts;
    }
}
