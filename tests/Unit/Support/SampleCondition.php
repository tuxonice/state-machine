<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Conditions\ConditionInterface;

class SampleCondition implements ConditionInterface
{
    /**
     * @param array<mixed> $data
     *
     * @return bool
     */
    public function check(array $data): bool
    {
        return true;
    }
}
