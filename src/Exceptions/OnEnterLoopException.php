<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

class OnEnterLoopException extends StateMachineException
{
    public static function afterTransitions(int $limit, string $state): self
    {
        return new self(
            "More than {$limit} onEnter transitions in a row, stopped in state '{$state}'. "
            . 'The definition probably has a cycle of onEnter events.'
        );
    }
}
