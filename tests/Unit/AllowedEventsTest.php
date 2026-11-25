<?php

namespace Tlab\Tests;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\Tests\Support\EventAllowedByData;
use Tlab\Tests\Support\Order;
use Tlab\Tests\Support\SpyCondition;

/**
 * The allowed* listings evaluate guards, unlike the available* ones, so a UI only offers what would succeed.
 */
class AllowedEventsTest extends TestCase
{
    private StateMachineRunner $runner;

    protected function setUp(): void
    {
        SpyCondition::$seen = [];
        $this->runner = new StateMachineRunner((new DefinitionReader())->readArray([
            'name' => 'allowed',
            'states' => [['name' => 'Open', 'isCurrent' => true], ['name' => 'Shipped'], ['name' => 'Refunded'], ['name' => 'Closed']],
            'transitions' => [
                ['source' => 'Open', 'target' => 'Shipped', 'event' => 'ship', 'condition' => EventAllowedByData::class],
                ['source' => 'Open', 'target' => 'Refunded', 'event' => 'refund', 'condition' => EventAllowedByData::class],
                ['source' => 'Open', 'target' => 'Closed', 'event' => 'cancel', 'condition' => SpyCondition::class],
                ['source' => 'Open', 'target' => 'Closed', 'event' => 'close'],
                ['source' => 'Open', 'target' => 'Open', 'event' => 'remind', 'condition' => EventAllowedByData::class],
            ],
            'events' => [
                ['name' => 'ship', 'manual' => true],
                ['name' => 'refund', 'manual' => true],
                ['name' => 'cancel', 'manual' => true],
                ['name' => 'close', 'manual' => true],
                ['name' => 'remind', 'timeout' => '1 hour'],
            ],
        ]));
    }

    public function testOnlyEventsWhoseGuardPassesAreAllowed(): void
    {
        $this->assertSame(['ship', 'cancel', 'close'], $this->runner->allowedEvents('Open', ['allowed' => ['ship']]));
    }

    public function testAvailableEventsStillIgnoreGuards(): void
    {
        $this->assertSame(['ship', 'refund', 'cancel', 'close', 'remind'], $this->runner->availableEvents('Open'));
    }

    public function testAllowedManualEventsLeaveOutEventsNobodyTriggers(): void
    {
        $allowed = $this->runner->allowedManualEvents('Open', ['allowed' => ['ship', 'remind']]);

        $this->assertSame(['ship', 'cancel', 'close'], $allowed);
    }

    public function testAllowedEventsForUseTheSubjectsStateAndPassTheSubjectToGuards(): void
    {
        $order = new Order('Open');

        $allowed = $this->runner->allowedEventsFor($order, ['allowed' => ['refund']]);

        $this->assertSame(['refund', 'cancel', 'close'], $allowed);
        $this->assertSame($order, SpyCondition::$seen[0]->subject);
    }

    public function testAllowedManualEventsForPassTheSubjectToGuards(): void
    {
        $order = new Order('Open');

        $allowed = $this->runner->allowedManualEventsFor($order, ['allowed' => ['remind']]);

        $this->assertSame(['cancel', 'close'], $allowed);
        $this->assertSame($order, SpyCondition::$seen[0]->subject);
    }

    public function testAStateWithNoTransitionsAllowsNothing(): void
    {
        $this->assertSame([], $this->runner->allowedEvents('Closed', ['allowed' => ['ship']]));
    }

    public function testAnUnknownStateIsRejected(): void
    {
        $this->expectException(UnknownStateException::class);
        $this->runner->allowedEvents('Nope');
    }
}
