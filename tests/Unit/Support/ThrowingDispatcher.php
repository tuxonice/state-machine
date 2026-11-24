<?php

namespace Tlab\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Tlab\StateMachine\Events\AfterTransition;

/**
 * An AfterTransition listener that fails when the subject enters a given state, like a mail service that is down.
 */
class ThrowingDispatcher implements EventDispatcherInterface
{
    public function __construct(private string $failOnTarget)
    {
    }

    public function dispatch(object $event): object
    {
        if ($event instanceof AfterTransition && $event->target === $this->failOnTarget) {
            throw new RuntimeException("listener failed after entering {$event->target}");
        }

        return $event;
    }
}
