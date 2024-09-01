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
                'from' => 'from-event',
                'to' => 'to-event',
                'event' => 'test-event',
                'condition' => '\\sample\\condition',
            ]
        );

        self::assertEquals('from-event', $transition->getFrom());
        self::assertEquals('to-event', $transition->getTo());
        self::assertEquals('test-event', $transition->getEvent());
    }
}
