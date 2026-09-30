<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Resolver;

use Tlab\StateMachine\Conditions\ConditionInterface;

class ConditionResolver extends AbstractResolver implements ConditionResolverInterface
{
    public function resolve(string $class): ConditionInterface
    {
        return $this->resolveInstance($class, ConditionInterface::class);
    }
}
