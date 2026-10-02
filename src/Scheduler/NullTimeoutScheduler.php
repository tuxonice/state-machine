<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use DateTimeImmutable;

/**
 * Default scheduler: timeouts are ignored.
 */
class NullTimeoutScheduler implements TimeoutScheduler
{
    public function schedule(object $subject, string $state, string $event, DateTimeImmutable $dueAt): void
    {
    }

    public function cancel(object $subject): void
    {
    }

    public function pullDue(DateTimeImmutable $now): array
    {
        return [];
    }
}
