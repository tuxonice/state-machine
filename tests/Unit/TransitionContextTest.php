<?php

namespace Tlab\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Scheduler\InMemoryTimeoutScheduler;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\Tests\Support\Order;
use Tlab\Tests\Support\SpyCommand;
use Tlab\Tests\Support\SpyCondition;

/**
 * Conditions and commands receive the subject and the transition they belong to, not just the caller's data.
 */
class TransitionContextTest extends TestCase
{
    private InMemoryTimeoutScheduler $scheduler;

    private StateMachineRunner $runner;

    protected function setUp(): void
    {
        SpyCondition::$seen = [];
        SpyCommand::$seen = [];
        $this->scheduler = new InMemoryTimeoutScheduler();
        $this->runner = new StateMachineRunner((new DefinitionReader())->readArray([
            'name' => 'context',
            'states' => [
                ['name' => 'New', 'isCurrent' => true], ['name' => 'Paid'], ['name' => 'Shipped'], ['name' => 'Expired'],
            ],
            'transitions' => [
                ['source' => 'New', 'target' => 'Paid', 'event' => 'pay', 'condition' => SpyCondition::class, 'command' => SpyCommand::class],
                ['source' => 'Paid', 'target' => 'Shipped', 'event' => 'ship', 'condition' => SpyCondition::class],
                ['source' => 'New', 'target' => 'Expired', 'event' => 'expire', 'condition' => SpyCondition::class],
            ],
            'events' => [
                ['name' => 'pay', 'manual' => true, 'command' => SpyCommand::class],
                ['name' => 'ship', 'onEnter' => true],
                ['name' => 'expire', 'timeout' => '1 hour'],
            ],
        ]), scheduler: $this->scheduler);
    }

    public function testAConditionSeesTheSubjectTheDataAndItsTransition(): void
    {
        $order = new Order('New');

        $this->runner->applyTo($order, 'pay', ['actor' => 'ana']);

        $context = SpyCondition::$seen[0];
        $this->assertSame($order, $context->subject);
        $this->assertSame(['actor' => 'ana'], $context->data);
        $this->assertSame('context', $context->machineName);
        $this->assertSame('New', $context->source);
        $this->assertSame('pay', $context->event);
        $this->assertSame('Paid', $context->target);
    }

    public function testEventAndTransitionCommandsSeeTheSameContextAsTheirTransition(): void
    {
        $order = new Order('New');

        $this->runner->applyTo($order, 'pay', ['actor' => 'ana']);

        $this->assertCount(2, SpyCommand::$seen); // the event command, then the transition command
        foreach (SpyCommand::$seen as $context) {
            $this->assertSame($order, $context->subject);
            $this->assertSame(['New', 'pay', 'Paid'], [$context->source, $context->event, $context->target]);
            $this->assertSame(['actor' => 'ana'], $context->data);
        }
    }

    public function testAnOnEnterConditionSeesTheSubjectAndItsOwnTransition(): void
    {
        $order = new Order('New');

        $this->runner->applyTo($order, 'pay'); // New -> Paid, then ship fires onEnter

        $onEnter = SpyCondition::$seen[1];
        $this->assertSame($order, $onEnter->subject);
        $this->assertSame(['Paid', 'ship', 'Shipped'], [$onEnter->source, $onEnter->event, $onEnter->target]);
    }

    public function testCanApplyToPassesTheSubject(): void
    {
        $order = new Order('New');

        $this->runner->canApplyTo($order, 'pay');

        $this->assertSame($order, SpyCondition::$seen[0]->subject);
    }

    public function testATimeoutConditionSeesTheSubject(): void
    {
        $order = new Order('New');
        $this->runner->scheduleTimeouts($order, new DateTimeImmutable('2099-01-01 10:00'));

        $this->runner->processTimeouts(new DateTimeImmutable('2099-01-01 11:00'));

        $this->assertSame($order, SpyCondition::$seen[0]->subject);
        $this->assertSame('expire', SpyCondition::$seen[0]->event);
    }

    public function testWithoutASubjectTheContextHasNone(): void
    {
        $this->runner->apply('New', 'pay', ['actor' => 'ana']);

        $this->assertNull(SpyCondition::$seen[0]->subject);
        $this->assertNull(SpyCommand::$seen[0]->subject);
        $this->assertSame(['actor' => 'ana'], SpyCondition::$seen[0]->data);
    }
}
