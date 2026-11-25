<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Conditions\ConditionInterface;
use Tlab\StateMachine\TransitionContext;

class SampleCondition implements ConditionInterface
{
    public function check(TransitionContext $context): bool
    {
        return true;
    }
}
