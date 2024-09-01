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
}
