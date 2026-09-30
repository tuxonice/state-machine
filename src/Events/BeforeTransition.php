<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Events;

class BeforeTransition
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        public readonly string $machineName,
        public readonly string $source,
        public readonly string $event,
        public readonly string $target,
        public readonly array $data,
        public readonly ?object $subject,
    ) {
    }
}
