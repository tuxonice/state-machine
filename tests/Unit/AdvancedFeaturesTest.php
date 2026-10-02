<?php

namespace Tlab\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Exceptions\MachineRegistryException;
use Tlab\StateMachine\Exceptions\OnEnterLoopException;
use Tlab\StateMachine\Exceptions\ValidationException;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Registry\StateMachineRegistry;
use Tlab\StateMachine\Scheduler\InMemoryTimeoutScheduler;
use Tlab\StateMachine\Scheduler\TimeoutParser;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\StateMachine\TransitionStatus;
use Tlab\Tests\Support\LogCommand;
use Tlab\Tests\Support\Order;

class AdvancedFeaturesTest extends TestCase
{
    private InMemoryTimeoutScheduler $scheduler;

    private StateMachineRunner $runner;

    protected function setUp(): void
    {
        LogCommand::$log = [];
        $this->scheduler = new InMemoryTimeoutScheduler();
        $this->runner = StateMachineRunner::fromJson($this->fixture('advanced.json'), scheduler: $this->scheduler);
    }

    public function testOnEnterEventFiresAfterEnteringTheState(): void
    {
        $result = $this->runner->apply('New', 'pay');

        // check is onEnter in Paid, its first transition is blocked by a false condition, so the else one wins
        $this->assertSame(TransitionStatus::Moved, $result->getStatus());
        $this->assertSame('Shipped', $result->getState());
        $this->assertSame(['pay', 'check'], $result->getEvents());
    }

    public function testOnEnterStateIsWrittenToTheSubject(): void
    {
        $order = new Order('New');

        $this->runner->applyTo($order, 'pay');

        $this->assertSame('Shipped', $order->getState());
    }

    public function testOnEnterEventWithFailingConditionLeavesTheSubjectInTheState(): void
    {
        $definition = json_decode($this->fixture('advanced.json'), true);
        // drop the unconditional "else" transition of check
        $definition['transitions'] = array_values(array_diff_key($definition['transitions'], [4 => true]));
        $runner = new StateMachineRunner((new DefinitionReader())->readArray($definition));

        $result = $runner->apply('New', 'pay');

        $this->assertSame('Paid', $result->getState());
        $this->assertSame(['pay'], $result->getEvents());
    }

    public function testRunsOnEnterChainOnlyWhenAMoveHappened(): void
    {
        $result = $this->runner->apply('New', 'complete');

        $this->assertSame(TransitionStatus::NoTransition, $result->getStatus());
        $this->assertSame([], $result->getEvents());
    }

    public function testOnEnterCycleIsStopped(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('on-enter-loop.json'));

        $this->expectException(OnEnterLoopException::class);
        $runner->apply('B', 'pong');
    }

    public function testAvailableManualEvents(): void
    {
        $this->assertSame(['cancel'], $this->runner->availableManualEvents('New'));
        $this->assertSame(['complete'], $this->runner->availableManualEvents('Shipped'));
        $this->assertSame([], $this->runner->availableManualEvents('Paid'));
        $this->assertSame(['cancel'], $this->runner->availableManualEventsFor(new Order('New')));
    }

    public function testTransitionCommandRunsAfterTheEventCommand(): void
    {
        $this->runner->apply('New', 'pay');

        $this->assertSame(['run', 'run'], LogCommand::$log);
    }

    public function testTransitionCommandDoesNotRunWhenNotMoving(): void
    {
        $this->runner->apply('Shipped', 'pay');

        $this->assertSame([], LogCommand::$log);
    }

    public function testTransitionCommandIsKeptByToJson(): void
    {
        $machine = (new DefinitionReader())->readFile(dirname(__DIR__) . '/Fixtures/advanced.json');
        $again = (new DefinitionReader())->read($machine->toJson());

        $this->assertSame('Tlab\Tests\Support\LogCommand', $again->getTransitions()[0]->getCommand());
    }

    public function testEnteringAStateSchedulesItsTimeouts(): void
    {
        $order = new Order('Paid');
        $this->runner->applyTo($order, 'check'); // Paid -> Shipped, which has the "complete" timeout

        $scheduled = $this->scheduler->all();
        $this->assertCount(1, $scheduled);
        $this->assertSame('complete', $scheduled[0]->getEvent());
        $this->assertSame('Shipped', $scheduled[0]->getState());
        $this->assertSame($order, $scheduled[0]->getSubject());
    }

    public function testProcessTimeoutsAppliesDueEventsOnly(): void
    {
        $now = new DateTimeImmutable('2026-01-01 10:00:00');
        $order = new Order('New');
        $this->runner->scheduleTimeouts($order, $now);

        $this->assertSame([], $this->runner->processTimeouts($now->modify('+1 day')));
        $this->assertSame('New', $order->getState());

        $results = $this->runner->processTimeouts($now->modify('+2 days'));

        $this->assertCount(1, $results);
        $this->assertSame('Cancelled', $order->getState());
        $this->assertSame([], $this->scheduler->all());
    }

    public function testMovingCancelsTheTimeoutsOfTheLeftState(): void
    {
        $order = new Order('New');
        $this->runner->scheduleTimeouts($order, new DateTimeImmutable('2026-01-01'));

        $this->runner->applyTo($order, 'cancel');

        $this->assertSame([], $this->scheduler->all());
    }

    public function testProcessTimeoutsSkipsSubjectsThatLeftTheState(): void
    {
        $now = new DateTimeImmutable('2026-01-01');
        $order = new Order('New');
        $this->scheduler->schedule($order, 'New', 'expire', $now);
        $order->setState('Paid');

        $this->assertSame([], $this->runner->processTimeouts($now));
        $this->assertSame('Paid', $order->getState());
    }

    public function testTimeoutsAreIgnoredByDefault(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('advanced.json'));
        $order = new Order('New');

        $runner->scheduleTimeouts($order);

        $this->assertSame([], $runner->processTimeouts(new DateTimeImmutable('+10 years')));
    }

    public function testInvalidTimeoutIsRejectedByTheSchema(): void
    {
        $definition = json_decode($this->fixture('advanced.json'), true);
        $definition['events'][2]['timeout'] = 'soon';

        $this->expectException(ValidationException::class);
        (new DefinitionReader())->readArray($definition);
    }

    public function testTimeoutParser(): void
    {
        $from = new DateTimeImmutable('2026-01-01 00:00:00');

        $this->assertEquals(new DateTimeImmutable('2026-01-01 00:30:00'), TimeoutParser::dueAt($from, '30 minutes'));
        $this->assertEquals(new DateTimeImmutable('2026-01-02 00:00:00'), TimeoutParser::dueAt($from, '1day'));
        $this->assertFalse(TimeoutParser::isValid('a while'));
        $this->expectException(\InvalidArgumentException::class);
        TimeoutParser::dueAt($from, 'a while');
    }

    public function testRegistryFindsMachinesByName(): void
    {
        $registry = new StateMachineRegistry();
        $registry->register($this->runner)->register($this->runner, 'other');

        $this->assertSame($this->runner, $registry->get('advanced'));
        $this->assertSame(['advanced', 'other'], $registry->names());
        $this->assertTrue($registry->has('other'));
        $this->assertFalse($registry->has('missing'));
    }

    public function testRegistryRejectsUnknownAndDuplicateNames(): void
    {
        $registry = new StateMachineRegistry();
        $registry->register($this->runner);

        try {
            $registry->register($this->runner);
            $this->fail('Duplicate name accepted');
        } catch (MachineRegistryException $e) {
            $this->assertStringContainsString('already registered', $e->getMessage());
        }

        $this->expectException(MachineRegistryException::class);
        $registry->get('missing');
    }

    private function fixture(string $name): string
    {
        return file_get_contents(dirname(__DIR__) . '/Fixtures/' . $name);
    }
}
