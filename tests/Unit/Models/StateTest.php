<?php

namespace Tlab\Tests\Models;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Models\State;

class StateTest extends TestCase
{
    public function testCanSetStateName(): void
    {
        $state = new State('test state');
        self::assertSame('test state', $state->getName());
    }
}
