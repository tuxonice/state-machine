<?php

namespace Tlab\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Exceptions\TimeoutProcessingException;
use Tlab\StateMachine\Scheduler\InMemoryTimeoutScheduler;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\StateMachine\TransitionStatus;
use Tlab\Tests\Support\FailingStorage;
use Tlab\Tests\Support\Order;
use Tlab\Tests\Support\PullOnlyTimeoutScheduler;

/**
 * processTimeouts() when some timeouts fail: the others must still be applied, and nothing may be lost silently.
 */
class TimeoutProcessingTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-01 10:00:00');
    }

    public function testAFailingTimeoutDoesNotStopTheOthers(): void
    {
        $poison = new Order('New');
        $healthy = new Order('New');
        $runner = $this->runner(new InMemoryTimeoutScheduler(), new FailingStorage($poison));
        $runner->scheduleTimeouts($poison, $this->now);   // "expire" after 2 days, first in line
        $runner->scheduleTimeouts($healthy, $this->now);

        try {
            $runner->processTimeouts($this->now->modify('+3 days'));
            $this->fail('the failure should be reported');
        } catch (TimeoutProcessingException $e) {
            $this->assertCount(1, $e->getResults());
            $this->assertSame('Cancelled', $e->getResults()[0]->getState());
            $this->assertCount(1, $e->getFailures());
            $this->assertSame($poison, $e->getFailures()[0]->getTimeout()->getSubject());
            $this->assertSame('write failed', $e->getFailures()[0]->getError()->getMessage());
        }

        $this->assertSame('Cancelled', $healthy->getState());
        $this->assertSame('New', $poison->getState());
    }

    public function testAFailedTimeoutStaysScheduledAndIsRetriedByTheNextRun(): void
    {
        $poison = new Order('New');
        $healthy = new Order('New');
        $scheduler = new InMemoryTimeoutScheduler();
        $storage = new FailingStorage($poison);
        $runner = $this->runner($scheduler, $storage);
        $runner->scheduleTimeouts($poison, $this->now);
        $runner->scheduleTimeouts($healthy, $this->now);

        try {
            $runner->processTimeouts($this->now->modify('+3 days'));
            $this->fail('the failure should be reported');
        } catch (TimeoutProcessingException) {
        }

        $left = $scheduler->all();
        $this->assertCount(1, $left);
        $this->assertSame($poison, $left[0]->getSubject());

        $storage->poison = null; // the database is back
        $results = $runner->processTimeouts($this->now->modify('+3 days'));

        $this->assertCount(1, $results);
        $this->assertSame('Cancelled', $poison->getState());
        $this->assertSame([], $scheduler->all());
    }

    public function testATimeoutWhoseSubjectLeftTheStateIsAcknowledged(): void
    {
        $order = new Order('Paid');
        $scheduler = new InMemoryTimeoutScheduler();
        $scheduler->schedule($order, 'New', 'expire', $this->now); // scheduled while the order was still New

        $results = $this->runner($scheduler, new FailingStorage(null))->processTimeouts($this->now);

        $this->assertSame([], $results);
        $this->assertSame([], $scheduler->all());
    }

    public function testATimeoutBlockedByItsConditionIsDroppedNotRetried(): void
    {
        $scheduler = new InMemoryTimeoutScheduler();
        $runner = StateMachineRunner::fromJson(json_encode([
            'name' => 'guarded-timeout',
            'states' => [['name' => 'Open', 'isCurrent' => true], ['name' => 'Closed']],
            'transitions' => [
                ['source' => 'Open', 'target' => 'Closed', 'event' => 'expire', 'condition' => FalseCondition::class],
            ],
            'events' => [['name' => 'expire', 'timeout' => '1 hour']],
        ], JSON_THROW_ON_ERROR), scheduler: $scheduler);
        $order = new Order('Open');
        $runner->scheduleTimeouts($order, $this->now);

        $results = $runner->processTimeouts($this->now->modify('+2 hours'));

        $this->assertSame(TransitionStatus::Blocked, $results[0]->getStatus());
        $this->assertSame('Open', $order->getState());
        $this->assertSame([], $scheduler->all());
    }

    public function testASchedulerWithOnlyPullDueStillWorksAndReportsFailures(): void
    {
        $poison = new Order('New');
        $healthy = new Order('New');
        $scheduler = new PullOnlyTimeoutScheduler();
        $runner = StateMachineRunner::fromJson(
            (string) file_get_contents(__DIR__ . '/../Fixtures/advanced.json'),
            storage: new FailingStorage($poison),
            scheduler: $scheduler,
        );
        $runner->scheduleTimeouts($poison, $this->now);
        $runner->scheduleTimeouts($healthy, $this->now);

        try {
            $runner->processTimeouts($this->now->modify('+3 days'));
            $this->fail('the failure should be reported');
        } catch (TimeoutProcessingException $e) {
            $this->assertSame($poison, $e->getFailures()[0]->getTimeout()->getSubject());
        }

        $this->assertSame('Cancelled', $healthy->getState());
        // pullDue() removed it before it was applied, so the exception is the only trace of the failed one
        $this->assertSame([], $scheduler->timeouts);
    }

    private function runner(InMemoryTimeoutScheduler $scheduler, FailingStorage $storage): StateMachineRunner
    {
        return StateMachineRunner::fromJson(
            (string) file_get_contents(__DIR__ . '/../Fixtures/advanced.json'),
            storage: $storage,
            scheduler: $scheduler,
        );
    }
}
