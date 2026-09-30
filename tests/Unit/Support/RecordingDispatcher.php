<?php

namespace Tlab\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tlab\Tests\RecordingCommand;

class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var array<array{object,int}> event and the number of commands run when it was dispatched */
    public array $dispatched = [];

    public function dispatch(object $event): object
    {
        $this->dispatched[] = [$event, RecordingCommand::$runs];

        return $event;
    }
}
