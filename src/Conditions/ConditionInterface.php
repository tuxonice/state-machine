<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Conditions;

use Tlab\StateMachine\TransitionContext;

interface ConditionInterface
{
    /**
     * Whether the transition in the context may be taken.
     *
     * It is also called by can() and canApplyTo(), so it should only read, not change anything.
     */
    public function check(TransitionContext $context): bool;
}
