<?php

namespace Tlab\Tests\Scheduler;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Scheduler\InMemoryTimeoutScheduler;
use Tlab\Tests\Support\Order;

class InMemoryTimeoutSchedulerTest extends TestCase
{
    public function testPullDueReturnsDueTimeoutsOldestFirstAndRemovesThem(): void
    {
        $scheduler = new InMemoryTimeoutScheduler();
        $order = new Order('New');
        $scheduler->schedule($order, 'New', 'late', new DateTimeImmutable('2026-01-01 12:00'));
        $scheduler->schedule($order, 'New', 'early', new DateTimeImmutable('2026-01-01 11:00'));
        $scheduler->schedule($order, 'New', 'future', new DateTimeImmutable('2026-01-02 00:00'));

        $due = $scheduler->pullDue(new DateTimeImmutable('2026-01-01 13:00'));

        $this->assertSame(['early', 'late'], array_map(fn($t) => $t->getEvent(), $due));
        $this->assertSame(['future'], array_map(fn($t) => $t->getEvent(), $scheduler->all()));
    }

    public function testClaimDueKeepsTimeoutsUntilEachIsAcknowledged(): void
    {
        $scheduler = new InMemoryTimeoutScheduler();
        $scheduler->schedule(new Order('New'), 'New', 'first', new DateTimeImmutable('2026-01-01 10:00'));
        $scheduler->schedule(new Order('New'), 'New', 'second', new DateTimeImmutable('2026-01-01 11:00'));
        $now = new DateTimeImmutable('2026-01-01 12:00');

        $claimed = $scheduler->claimDue($now);
        $this->assertCount(2, $scheduler->all());

        $scheduler->acknowledge($claimed[0]);

        $this->assertSame(['second'], array_map(fn($t) => $t->getEvent(), $scheduler->claimDue($now)));
    }
}
