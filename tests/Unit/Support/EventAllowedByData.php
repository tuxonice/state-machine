<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Conditions\ConditionInterface;
use Tlab\StateMachine\TransitionContext;

/**
 * Passes when the caller lists the transition's event in $data['allowed'], like a permission check.
 */
class EventAllowedByData implements ConditionInterface
{
    public function check(TransitionContext $context): bool
    {
        return in_array($context->event, $context->data['allowed'] ?? [], true);
    }
}
