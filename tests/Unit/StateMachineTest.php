<?php

namespace Tlab\Tests;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Exceptions\UnknownEventException;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\StateMachine\TransitionStatus;

class StateMachineTest extends TestCase
{
    public function testCanMoveForTheNextState(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__) . '/Fixtures/sample.json');
        $stateMachineRunner = new StateMachineRunner($definitionJson);

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
        $stateMachine = new StateMachineRunner($definitionJson);
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
        $stateMachineRunner = new StateMachineRunner($definitionJson);

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
        $stateMachineRunner = new StateMachineRunner($definitionJson);

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
        $stateMachineRunner = new StateMachineRunner($definitionJson);

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
        $runner = new StateMachineRunner($this->fixture('sample-else.json'));

        $this->assertEquals('Cancelled', $runner->run('Pending', 'pay'));
    }

    public function testApplyReportsMovedWithTarget(): void
    {
        $runner = new StateMachineRunner($this->fixture('sample-1.json'));

        $result = $runner->apply('S1', 'EV1');

        $this->assertSame(TransitionStatus::Moved, $result->getStatus());
        $this->assertSame('S2', $result->getState());
        $this->assertTrue($result->hasMoved());
    }

    public function testApplyReportsBlockedWhenNoConditionPasses(): void
    {
        $runner = new StateMachineRunner($this->fixture('sample-else.json'));

        $result = $runner->apply('Paid', 'block');

        $this->assertSame(TransitionStatus::Blocked, $result->getStatus());
        $this->assertSame('Paid', $result->getState());
        $this->assertFalse($result->hasMoved());
    }

    public function testApplyReportsNoTransitionWhenEventIsNotAvailableInState(): void
    {
        $runner = new StateMachineRunner($this->fixture('sample-1.json'));

        $result = $runner->apply('S1', 'EV2');

        $this->assertSame(TransitionStatus::NoTransition, $result->getStatus());
        $this->assertSame('S1', $result->getState());
    }

    public function testUnknownStateThrows(): void
    {
        $runner = new StateMachineRunner($this->fixture('sample-1.json'));

        $this->expectException(UnknownStateException::class);
        $this->expectExceptionMessage("State 'Nope' does not exist");
        $runner->run('Nope', 'EV1');
    }

    public function testUnknownEventThrows(): void
    {
        $runner = new StateMachineRunner($this->fixture('sample-1.json'));

        $this->expectException(UnknownEventException::class);
        $this->expectExceptionMessage("Event 'Nope' does not exist");
        $runner->run('S1', 'Nope');
    }

    public function testCommandRunsOnlyWhenTransitionMoves(): void
    {
        RecordingCommand::$runs = 0;
        $runner = new StateMachineRunner($this->fixture('sample-else.json'));

        $runner->run('Paid', 'block');
        $this->assertSame(0, RecordingCommand::$runs);

        $runner->run('Pending', 'pay');
        $this->assertSame(1, RecordingCommand::$runs);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(dirname(__DIR__) . '/Fixtures/' . $name);
    }
}
