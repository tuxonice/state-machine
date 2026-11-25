<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Conditions\ConditionInterface;
use Tlab\StateMachine\TransitionContext;

/**
 * Records the context of every check and passes.
 */
class SpyCondition implements ConditionInterface
{
    /** @var TransitionContext[] */
    public static array $seen = [];

    public function check(TransitionContext $context): bool
    {
        self::$seen[] = $context;

        return true;
    }
}
