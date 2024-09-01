<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Conditions;

interface ConditionInterface
{
    /**
     * @param array<mixed> $data
     *
     * @return bool
     */
    public function check(array $data): bool;
}
