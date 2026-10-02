<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Resolver;

use Tlab\StateMachine\Conditions\ConditionInterface;
use Tlab\StateMachine\Exceptions\ResolutionException;

interface ConditionResolverInterface
{
    /**
     * @param string $class
     *
     * @throws ResolutionException
     */
    public function resolve(string $class): ConditionInterface;
}
