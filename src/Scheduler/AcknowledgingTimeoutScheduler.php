<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use DateTimeImmutable;

/**
 * A scheduler that keeps each timeout until it has been dealt with, so a timeout is not lost when applying it
 * fails or the worker dies half-way. processTimeouts() uses claimDue() and acknowledge() instead of pullDue()
 * when the scheduler implements this interface.
 */
interface AcknowledgingTimeoutScheduler extends TimeoutScheduler
{
    /**
     * Returns timeouts due at the given time without removing them.
     *
     * A store shared by several workers (database, queue) should hide the returned timeouts from other
     * workers for a while (a lease) and may return a limited batch. A timeout that is not acknowledged must
     * become due again later; giving up on one that keeps failing is up to the implementation.
     *
     * @return ScheduledTimeout[]
     */
    public function claimDue(DateTimeImmutable $now): array;

    /**
     * Removes a timeout returned by claimDue(), once it was applied or found obsolete.
     */
    public function acknowledge(ScheduledTimeout $timeout): void;
}
