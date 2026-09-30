<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Conditions\ConditionInterface;

class ConfigurableCondition implements ConditionInterface
{
    public function __construct(private bool $result)
    {
    }

    public function check(array $data): bool
    {
        return $this->result;
    }
}
