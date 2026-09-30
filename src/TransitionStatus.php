<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

enum TransitionStatus
{
    /** A transition matched and its condition (if any) passed. */
    case Moved;

    /** Transitions exist for the state and event, but no condition passed. */
    case Blocked;

    /** No transition is defined for the state and event. */
    case NoTransition;
}
