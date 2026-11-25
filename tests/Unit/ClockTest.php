<?php

namespace Tlab\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Scheduler\InMemoryTimeoutScheduler;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\Tests\Support\FrozenClock;
use Tlab\Tests\Support\Order;

/**
 * Timeouts follow the injected clock, so timer behaviour can be tested without waiting or patching time.
 * The clock is set far from the real date, so a runner that still read the system clock would fail.
 */
class ClockTest extends TestCase
{
    private InMemoryTimeoutScheduler $scheduler;

    private FrozenClock $clock;

    private StateMachineRunner $runner;

    protected function setUp(): void
    {
        $this->scheduler = new InMemoryTimeoutScheduler();
        $this->clock = new FrozenClock(new DateTimeImmutable('2099-01-01 10:00:00'));
        $this->runner = StateMachineRunner::fromJson(
            (string) file_get_contents(__DIR__ . '/../Fixtures/advanced.json'),
            scheduler: $this->scheduler,
            clock: $this->clock,
        );
    }

    public function testEnteringAStateSchedulesItsTimeoutsFromTheClock(): void
    {
        $this->runner->applyTo(new Order('Paid'), 'check'); // Paid -> Shipped, "complete" after 1 hour

        $this->assertEquals(new DateTimeImmutable('2099-01-01 11:00:00'), $this->scheduler->all()[0]->getDueAt());
    }

    public function testProcessTimeoutsWithoutATimeUsesTheClock(): void
    {
        $order = new Order('Paid');
        $this->runner->applyTo($order, 'check');

        $this->clock->set(new DateTimeImmutable('2099-01-01 10:59:59'));
        $this->assertSame([], $this->runner->processTimeouts());

        $this->clock->set(new DateTimeImmutable('2099-01-01 11:00:00'));
        $this->assertCount(1, $this->runner->processTimeouts());
        $this->assertSame('Done', $order->getState());
    }
}
