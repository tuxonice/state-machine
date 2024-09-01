<?php

namespace Tlab\Tests\Models;

use Tlab\StateMachine\Models\Event;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testCreateEventFromArray(): void
    {
        $event = Event::createFromArray(
            [
                'name' => 'test-name',
                'command' => '\\sample\\command',
            ]
        );

        self::assertEquals('test-name', $event->getName());
        self::assertEquals('\\sample\\command', $event->getCommand());
    }
}
