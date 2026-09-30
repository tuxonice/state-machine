<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Events;

/**
 * Dispatched when transitions exist for the state and event, but no condition passed.
 */
class TransitionBlocked
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        public readonly string $machineName,
        public readonly string $source,
        public readonly string $event,
        public readonly array $data,
        public readonly ?object $subject,
    ) {
    }
}
