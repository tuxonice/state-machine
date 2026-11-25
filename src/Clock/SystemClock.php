<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * The real time. The runner's default clock.
 */
class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
