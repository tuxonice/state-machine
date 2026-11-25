<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Commands\CommandInterface;
use Tlab\StateMachine\TransitionContext;

class SampleCommand implements CommandInterface
{
    public function run(TransitionContext $context): void
    {
        // eg: Send notification by email
    }
}
