<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Conditions\ConditionInterface;
use Tlab\StateMachine\TransitionContext;

class ConfigurableCondition implements ConditionInterface
{
    public function __construct(private bool $result)
    {
    }

    public function check(TransitionContext $context): bool
    {
        return $this->result;
    }
}
