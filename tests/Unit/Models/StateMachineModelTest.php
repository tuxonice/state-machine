<?php

namespace Tlab\Tests\Models;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Models\Event;
use Tlab\StateMachine\Models\State;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Models\Transition;

class StateMachineModelTest extends TestCase
{
    private function machine(): StateMachine
    {
        return new StateMachine(
            'Test',
            [new State('A', true), new State('B'), new State('C')],
            [
                Transition::createFromArray(['source' => 'A', 'target' => 'B', 'event' => 'go']),
                Transition::createFromArray(['source' => 'A', 'target' => 'C', 'event' => 'go']),
                Transition::createFromArray(['source' => 'A', 'target' => 'C', 'event' => 'skip']),
                Transition::createFromArray(['source' => 'B', 'target' => 'C', 'event' => 'skip']),
            ],
            [Event::createFromArray(['name' => 'go']), Event::createFromArray(['name' => 'skip'])],
        );
    }

    public function testExposesWhatItWasBuiltWith(): void
    {
        $machine = $this->machine();

        self::assertSame('Test', $machine->getName());
        self::assertCount(3, $machine->getStates());
        self::assertCount(4, $machine->getTransitions());
        self::assertCount(2, $machine->getEvents());
        self::assertSame('A', $machine->getCurrentState());
    }

    public function testHasStateAndHasEvent(): void
    {
        $machine = $this->machine();

        self::assertTrue($machine->hasState('B'));
        self::assertFalse($machine->hasState('Z'));
        self::assertTrue($machine->hasEvent('go'));
        self::assertFalse($machine->hasEvent('nope'));
    }

    public function testGetEvent(): void
    {
        self::assertSame('go', $this->machine()->getEvent('go')?->getName());
        self::assertNull($this->machine()->getEvent('nope'));
    }

    public function testGetTransitionsForKeepsDefinitionOrder(): void
    {
        $targets = array_map(
            fn(Transition $transition) => $transition->getTarget(),
            $this->machine()->getTransitionsFor('A', 'go')
        );

        self::assertSame(['B', 'C'], $targets);
    }

    public function testGetAvailableEventsIsDistinctAndInDefinitionOrder(): void
    {
        self::assertSame(['go', 'skip'], $this->machine()->getAvailableEvents('A'));
        self::assertSame(['skip'], $this->machine()->getAvailableEvents('B'));
        self::assertSame([], $this->machine()->getAvailableEvents('C'));
    }
}
