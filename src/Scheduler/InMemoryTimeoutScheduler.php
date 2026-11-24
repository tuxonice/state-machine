<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use DateTimeImmutable;

/**
 * Keeps timeouts in memory. Useful for tests and long-running processes.
 *
 * A claimed timeout stays until it is acknowledged, so one that failed is claimed again on the next run.
 * There is no lease: it is meant for a single process.
 */
class InMemoryTimeoutScheduler implements AcknowledgingTimeoutScheduler
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
        $due = $this->claimDue($now);
        $this->timeouts = array_values(array_filter(
            $this->timeouts,
            fn(ScheduledTimeout $timeout) => $timeout->getDueAt() > $now
        ));

        return $due;
    }

    public function claimDue(DateTimeImmutable $now): array
    {
        $due = array_values(array_filter(
            $this->timeouts,
            fn(ScheduledTimeout $timeout) => $timeout->getDueAt() <= $now
        ));

        usort($due, fn(ScheduledTimeout $a, ScheduledTimeout $b) => $a->getDueAt() <=> $b->getDueAt());

        return $due;
    }

    public function acknowledge(ScheduledTimeout $timeout): void
    {
        $this->timeouts = array_values(array_filter(
            $this->timeouts,
            fn(ScheduledTimeout $scheduled) => $scheduled !== $timeout
        ));
    }

    /**
     * @return ScheduledTimeout[]
     */
    public function all(): array
    {
        return $this->timeouts;
    }
}
