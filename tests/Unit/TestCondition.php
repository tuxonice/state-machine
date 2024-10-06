<?php

namespace Tlab\Tests;

use Tlab\StateMachine\Conditions\ConditionInterface;

class TestCondition implements ConditionInterface
{

    public function check(array $data): bool
    {
        return true;
    }
}