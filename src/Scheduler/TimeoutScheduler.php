<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use DateTimeImmutable;

/**
 * Remembers which events must be applied to which subjects, and when. Implement it on top of a
 * database or a queue so timeouts survive between processes; the runner only calls these three methods.
 */
interface TimeoutScheduler
{
    public function schedule(object $subject, string $state, string $event, DateTimeImmutable $dueAt): void;

    /**
     * Forgets everything scheduled for the subject.
     */
    public function cancel(object $subject): void;

    /**
     * Returns the timeouts due at the given time and removes them from the scheduler.
     *
     * @return ScheduledTimeout[]
     */
    public function pullDue(DateTimeImmutable $now): array;
}
