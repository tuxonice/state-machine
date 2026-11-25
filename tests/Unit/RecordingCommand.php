<?php

namespace Tlab\Tests;

use Tlab\StateMachine\Commands\CommandInterface;
use Tlab\StateMachine\TransitionContext;

class RecordingCommand implements CommandInterface
{
    public static int $runs = 0;

    public function run(TransitionContext $context): void
    {
        self::$runs++;
    }
}
