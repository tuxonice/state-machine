<?php

namespace Tlab\Tests\Support;

use DateTimeImmutable;
use Tlab\StateMachine\Scheduler\AcknowledgingTimeoutScheduler;
use Tlab\StateMachine\Scheduler\InMemoryTimeoutScheduler;
use Tlab\StateMachine\Scheduler\ScheduledTimeout;

/**
 * An acknowledging scheduler that hands out at most a few due timeouts per claim, like a database one with a batch size.
 */
class BatchingTimeoutScheduler implements AcknowledgingTimeoutScheduler
{
    public InMemoryTimeoutScheduler $inner;

    public int $claims = 0;

    public function __construct(private int $batchSize)
    {
        $this->inner = new InMemoryTimeoutScheduler();
    }

    public function schedule(object $subject, string $state, string $event, DateTimeImmutable $dueAt): void
    {
        $this->inner->schedule($subject, $state, $event, $dueAt);
    }

    public function cancel(object $subject): void
    {
        $this->inner->cancel($subject);
    }

    public function pullDue(DateTimeImmutable $now): array
    {
        return $this->inner->pullDue($now);
    }

    public function claimDue(DateTimeImmutable $now): array
    {
        $this->claims++;

        return array_slice($this->inner->claimDue($now), 0, $this->batchSize);
    }

    public function acknowledge(ScheduledTimeout $timeout): void
    {
        $this->inner->acknowledge($timeout);
    }
}
