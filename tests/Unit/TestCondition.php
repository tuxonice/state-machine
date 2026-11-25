<?php

namespace Tlab\Tests;

use Tlab\StateMachine\Conditions\ConditionInterface;
use Tlab\StateMachine\TransitionContext;

class TestCondition implements ConditionInterface
{
    public function check(TransitionContext $context): bool
    {
        return true;
    }
}
