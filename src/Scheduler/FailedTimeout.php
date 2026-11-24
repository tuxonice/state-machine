<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use Throwable;

/**
 * A due timeout that could not be applied, and why.
 */
class FailedTimeout
{
    public function __construct(private ScheduledTimeout $timeout, private Throwable $error)
    {
    }

    public function getTimeout(): ScheduledTimeout
    {
        return $this->timeout;
    }

    public function getError(): Throwable
    {
        return $this->error;
    }
}
