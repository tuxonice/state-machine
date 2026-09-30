<?php

namespace Tlab\Tests;

use Tlab\StateMachine\Conditions\ConditionInterface;

class FalseCondition implements ConditionInterface
{
    public function check(array $data): bool
    {
        return false;
    }
}
