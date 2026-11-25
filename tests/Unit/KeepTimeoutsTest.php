<?php

namespace Tlab\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Exceptions\ValidationException;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Scheduler\InMemoryTimeoutScheduler;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\Tests\Support\FrozenClock;
use Tlab\Tests\Support\Order;

/**
 * A self-transition flagged keepTimeouts leaves the state's running timers alone; without the flag it
 * restarts them, as every move does.
 */
class KeepTimeoutsTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2099-01-01 10:00:00'));
    }

    public function testAReminderSelfLoopWithKeepTimeoutsDoesNotPostponeTheCancel(): void
    {
        $runner = $this->runner(keepTimeouts: true);
        $order = new Order('New');
        $runner->applyTo($order, 'place'); // Pending: remind after 15 minutes, cancel after 30

        $this->at('10:15');
        $reminded = $runner->processTimeouts();
        $this->at('10:30');
        $runner->processTimeouts();

        $this->assertSame(['remind'], $reminded[0]->getEvents());
        $this->assertSame('Cancelled', $order->getState());
    }

    public function testASelfLoopWithoutTheFlagRestartsTheTimers(): void
    {
        $runner = $this->runner(keepTimeouts: false);
        $order = new Order('New');
        $runner->applyTo($order, 'place');

        $this->at('10:15');
        $runner->processTimeouts(); // remind: the cancel moves to 10:45
        $this->at('10:30');
        $runner->processTimeouts(); // remind again, nothing cancels

        $this->assertSame('Pending', $order->getState());
    }

    public function testKeepTimeoutsIsOnlyAllowedOnASelfTransition(): void
    {
        $definition = $this->definition(keepTimeouts: true);
        $definition['transitions'][0]['keepTimeouts'] = true; // New -> Pending

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('keepTimeouts');
        (new DefinitionReader())->readArray($definition);
    }

    public function testKeepTimeoutsSurvivesToJson(): void
    {
        $machine = (new DefinitionReader())->readArray($this->definition(keepTimeouts: true));

        $again = (new DefinitionReader())->read($machine->toJson());

        $this->assertTrue($again->getTransitions()[1]->keepsTimeouts());
        $this->assertFalse($again->getTransitions()[0]->keepsTimeouts());
    }

    private function at(string $time): void
    {
        $this->clock->set(new DateTimeImmutable("2099-01-01 {$time}:00"));
    }

    private function runner(bool $keepTimeouts): StateMachineRunner
    {
        return new StateMachineRunner(
            (new DefinitionReader())->readArray($this->definition($keepTimeouts)),
            scheduler: new InMemoryTimeoutScheduler(),
            clock: $this->clock,
        );
    }

    /** @return array<string, mixed> */
    private function definition(bool $keepTimeouts): array
    {
        $remind = ['source' => 'Pending', 'target' => 'Pending', 'event' => 'remind'];
        if ($keepTimeouts) {
            $remind['keepTimeouts'] = true;
        }

        return [
            'name' => 'reminders',
            'states' => [['name' => 'New', 'isCurrent' => true], ['name' => 'Pending'], ['name' => 'Cancelled']],
            'transitions' => [
                ['source' => 'New', 'target' => 'Pending', 'event' => 'place'],
                $remind,
                ['source' => 'Pending', 'target' => 'Cancelled', 'event' => 'cancel'],
            ],
            'events' => [
                ['name' => 'place', 'manual' => true],
                ['name' => 'remind', 'timeout' => '15 minutes'],
                ['name' => 'cancel', 'timeout' => '30 minutes'],
            ],
        ];
    }
}
