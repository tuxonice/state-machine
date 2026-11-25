<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Commands;

use Tlab\StateMachine\TransitionContext;

interface CommandInterface
{
    /**
     * Runs when the transition in the context is taken, before the new state is stored.
     */
    public function run(TransitionContext $context): void;
}
