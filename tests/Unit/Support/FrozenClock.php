<?php

namespace Tlab\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A clock that only moves when told to.
 */
class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
