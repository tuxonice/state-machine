<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Conditions;

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
