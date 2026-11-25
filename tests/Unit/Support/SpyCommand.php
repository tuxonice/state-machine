<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Commands\CommandInterface;
use Tlab\StateMachine\TransitionContext;

/**
 * Records the context of every run.
 */
class SpyCommand implements CommandInterface
{
    /** @var TransitionContext[] */
    public static array $seen = [];

    public function run(TransitionContext $context): void
    {
        self::$seen[] = $context;
    }
}
