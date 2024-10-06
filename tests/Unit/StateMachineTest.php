<?php

namespace Tlab\Tests;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\StateMachineRunner;

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
}
