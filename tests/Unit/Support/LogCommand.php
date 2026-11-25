<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Commands\CommandInterface;
use Tlab\StateMachine\TransitionContext;

class LogCommand implements CommandInterface
{
    /** @var string[] */
    public static array $log = [];

    public function run(TransitionContext $context): void
    {
        self::$log[] = 'run';
    }
}
