<?php

namespace Tlab\Tests\Models;

use Tlab\StateMachine\Models\Transition;
use PHPUnit\Framework\TestCase;

class TransitionTest extends TestCase
{
    public function testCreateTransitionFromArray(): void
    {
        $transition = Transition::createFromArray(
            [
                'source' => 'source-state',
                'target' => 'target-state',
                'event' => 'test-event',
                'condition' => '\\sample\\condition',
            ]
        );

        self::assertEquals('source-state', $transition->getSource());
        self::assertEquals('target-state', $transition->getTarget());
        self::assertEquals('test-event', $transition->getEvent());
    }
}
