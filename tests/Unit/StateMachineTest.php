<?php

namespace Tlab\Tests;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Commands\CommandInterface;
use Tlab\StateMachine\Events\AfterTransition;
use Tlab\StateMachine\Events\BeforeTransition;
use Tlab\StateMachine\Events\TransitionBlocked;
use Tlab\StateMachine\Exceptions\UnknownEventException;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Resolver\CommandResolver;
use Tlab\StateMachine\Resolver\ConditionResolver;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\StateMachine\Storage\InMemoryStateStorage;
use Tlab\StateMachine\TransitionStatus;
use Tlab\Tests\Support\ArrayContainer;
use Tlab\Tests\Support\ConfigurableCondition;
use Tlab\Tests\Support\Order;
use Tlab\Tests\Support\RecordingDispatcher;

class StateMachineTest extends TestCase
{
    public function testCanMoveForTheNextState(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__) . '/Fixtures/sample.json');
        $stateMachineRunner = StateMachineRunner::fromJson($definitionJson);

        $this->assertEquals(
            'Created',
            $stateMachineRunner->run(
                'New',
                'Create Order',
                [
                    'test-key-1' => 'test-value1'
                ]
            )
        );
    }

    public function testCanNotMoveForTheLastState(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__) . '/Fixtures/sample.json');
        $stateMachine = StateMachineRunner::fromJson($definitionJson);
        $this->assertEquals('New', $stateMachine->run(
            'New',
            'Start Shipment Preparation',
            [
                'test-key-1' => 'test-value1'
            ]
        ));
    }

    public function testCanMoveFromState1ToState2(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__) . '/Fixtures/sample-1.json');
        $stateMachineRunner = StateMachineRunner::fromJson($definitionJson);

        $this->assertEquals(
            'S2',
            $stateMachineRunner->run(
                'S1',
                'EV1',
                [
                    'test-key-1' => 'test-value1'
                ]
            )
        );
    }

    public function testCanNotMoveFromState1ToState3(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__) . '/Fixtures/sample-1.json');
        $stateMachineRunner = StateMachineRunner::fromJson($definitionJson);

        $this->assertEquals(
            'S1',
            $stateMachineRunner->run(
                'S1',
                'EV2',
                [
                    'test-key-1' => 'test-value1'
                ]
            )
        );
    }

    public function testIfCondition(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__) . '/Fixtures/sample-2.json');
        $stateMachineRunner = StateMachineRunner::fromJson($definitionJson);

        $this->assertEquals(
            'S3',
            $stateMachineRunner->run(
                'S2',
                'EV2',
                [
                    'test-key-1' => 'test-value1'
                ]
            )
        );
    }

    public function testElseBranchIsTakenWhenConditionFails(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-else.json'));

        $this->assertEquals('Cancelled', $runner->run('Pending', 'pay'));
    }

    public function testApplyReportsMovedWithTarget(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));

        $result = $runner->apply('S1', 'EV1');

        $this->assertSame(TransitionStatus::Moved, $result->getStatus());
        $this->assertSame('S2', $result->getState());
        $this->assertTrue($result->hasMoved());
    }

    public function testApplyReportsBlockedWhenNoConditionPasses(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-else.json'));

        $result = $runner->apply('Paid', 'block');

        $this->assertSame(TransitionStatus::Blocked, $result->getStatus());
        $this->assertSame('Paid', $result->getState());
        $this->assertFalse($result->hasMoved());
    }

    public function testApplyReportsNoTransitionWhenEventIsNotAvailableInState(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));

        $result = $runner->apply('S1', 'EV2');

        $this->assertSame(TransitionStatus::NoTransition, $result->getStatus());
        $this->assertSame('S1', $result->getState());
    }

    public function testUnknownStateThrows(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));

        $this->expectException(UnknownStateException::class);
        $this->expectExceptionMessage("State 'Nope' does not exist");
        $runner->run('Nope', 'EV1');
    }

    public function testUnknownEventThrows(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));

        $this->expectException(UnknownEventException::class);
        $this->expectExceptionMessage("Event 'Nope' does not exist");
        $runner->run('S1', 'Nope');
    }

    public function testCommandRunsOnlyWhenTransitionMoves(): void
    {
        RecordingCommand::$runs = 0;
        $runner = StateMachineRunner::fromJson($this->fixture('sample-else.json'));

        $runner->run('Paid', 'block');
        $this->assertSame(0, RecordingCommand::$runs);

        $runner->run('Pending', 'pay');
        $this->assertSame(1, RecordingCommand::$runs);
    }

    public function testCanBeBuiltFromADefinitionObject(): void
    {
        $machine = (new DefinitionReader())->read($this->fixture('sample-1.json'));

        $this->assertSame('S2', (new StateMachineRunner($machine))->run('S1', 'EV1'));
    }

    public function testConditionsAreResolvedThroughTheResolver(): void
    {
        $container = new ArrayContainer([FalseCondition::class => new ConfigurableCondition(true)]);
        $runner = StateMachineRunner::fromJson(
            $this->fixture('sample-else.json'),
            conditionResolver: new ConditionResolver($container)
        );

        // FalseCondition is swapped for a passing one by the container, so the first branch wins
        $this->assertSame('Paid', $runner->run('Pending', 'pay'));
    }

    public function testCommandsAreResolvedThroughTheResolver(): void
    {
        $spy = new class implements CommandInterface {
            public int $runs = 0;

            public function run(array $data): void
            {
                $this->runs++;
            }
        };
        $runner = StateMachineRunner::fromJson(
            $this->fixture('sample-else.json'),
            commandResolver: new CommandResolver(new ArrayContainer([RecordingCommand::class => $spy]))
        );

        $runner->run('Pending', 'pay');

        $this->assertSame(1, $spy->runs);
    }

    public function testDispatchesBeforeAndAfterAroundTheCommand(): void
    {
        RecordingCommand::$runs = 0;
        $dispatcher = new RecordingDispatcher();
        $runner = StateMachineRunner::fromJson(
            $this->fixture('sample-else.json'),
            dispatcher: $dispatcher
        );

        $runner->run('Pending', 'pay', ['id' => 1]);

        [[$before, $runsBefore], [$after, $runsAfter]] = $dispatcher->dispatched;
        $this->assertInstanceOf(BeforeTransition::class, $before);
        $this->assertInstanceOf(AfterTransition::class, $after);
        $this->assertSame(0, $runsBefore);
        $this->assertSame(1, $runsAfter);
        $this->assertSame('If-else machine', $before->machineName);
        $this->assertSame('Pending', $before->source);
        $this->assertSame('Cancelled', $before->target);
        $this->assertSame('pay', $before->event);
        $this->assertSame(['id' => 1], $before->data);
        $this->assertNull($before->subject);
    }

    public function testDispatchesBlockedWhenNoConditionPasses(): void
    {
        $dispatcher = new RecordingDispatcher();
        $runner = StateMachineRunner::fromJson($this->fixture('sample-else.json'), dispatcher: $dispatcher);

        $runner->run('Paid', 'block');

        $this->assertCount(1, $dispatcher->dispatched);
        $blocked = $dispatcher->dispatched[0][0];
        $this->assertInstanceOf(TransitionBlocked::class, $blocked);
        $this->assertSame('Paid', $blocked->source);
        $this->assertSame('block', $blocked->event);
    }

    public function testDispatchesNothingWhenThereIsNoTransition(): void
    {
        $dispatcher = new RecordingDispatcher();
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'), dispatcher: $dispatcher);

        $runner->run('S1', 'EV2');

        $this->assertSame([], $dispatcher->dispatched);
    }

    public function testCanChecksConditionsWithoutSideEffects(): void
    {
        RecordingCommand::$runs = 0;
        $runner = StateMachineRunner::fromJson($this->fixture('sample-else.json'));

        $this->assertTrue($runner->can('Pending', 'pay'));
        $this->assertFalse($runner->can('Paid', 'block'));
        $this->assertFalse($runner->can('Paid', 'pay'));
        $this->assertSame(0, RecordingCommand::$runs);
    }

    public function testAvailableEventsForAState(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));

        $this->assertSame(['EV1'], $runner->availableEvents('S1'));
        $this->assertSame([], $runner->availableEvents('S3'));
    }

    public function testAvailableEventsRejectsUnknownState(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));

        $this->expectException(UnknownStateException::class);
        $runner->availableEvents('Nope');
    }

    public function testApplyToMovesAStatefulSubject(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));
        $order = new Order('S1');

        $result = $runner->applyTo($order, 'EV1');

        $this->assertTrue($result->hasMoved());
        $this->assertSame('S2', $order->getState());
    }

    public function testApplyToLeavesSubjectUntouchedWhenItDoesNotMove(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));
        $order = new Order('S1');

        $result = $runner->applyTo($order, 'EV2');

        $this->assertSame(TransitionStatus::NoTransition, $result->getStatus());
        $this->assertSame('S1', $order->getState());
    }

    public function testApplyToStartsFromTheInitialStateWhenSubjectHasNone(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('minimal.json'));
        $order = new Order();

        $runner->applyTo($order, 'go');

        $this->assertSame('B', $order->getState());
    }

    public function testApplyToThrowsWhenThereIsNoInitialState(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));

        $this->expectException(UnknownStateException::class);
        $this->expectExceptionMessage('no initial state');
        $runner->applyTo(new Order(), 'EV1');
    }

    public function testApplyToPassesTheSubjectToTheDispatchedEvents(): void
    {
        $dispatcher = new RecordingDispatcher();
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'), dispatcher: $dispatcher);
        $order = new Order('S1');

        $runner->applyTo($order, 'EV1');

        $this->assertSame($order, $dispatcher->dispatched[0][0]->subject);
    }

    public function testCanAndAvailableEventsWorkOnSubjects(): void
    {
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'));
        $order = new Order('S1');

        $this->assertTrue($runner->canApplyTo($order, 'EV1'));
        $this->assertFalse($runner->canApplyTo($order, 'EV2'));
        $this->assertSame(['EV1'], $runner->availableEventsFor($order));
    }

    public function testCustomStorageIsUsedToReadAndWriteState(): void
    {
        $storage = new InMemoryStateStorage();
        $runner = StateMachineRunner::fromJson($this->fixture('sample-1.json'), storage: $storage);
        $plainObject = new \stdClass();
        $storage->write($plainObject, 'S1');

        $runner->applyTo($plainObject, 'EV1');

        $this->assertSame('S2', $storage->read($plainObject));
    }

    private function fixture(string $name): string
    {
        return file_get_contents(dirname(__DIR__) . '/Fixtures/' . $name);
    }
}
