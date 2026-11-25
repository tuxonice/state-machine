<?php

declare(strict_types=1);

namespace Tlab\StateMachine;

/**
 * What a condition or a command is given: the transition it belongs to, the caller's data, and the subject.
 *
 * The subject is null when the runner works on a bare state (apply(), can(), run()) instead of a subject
 * (applyTo(), canApplyTo(), onEnter chains and timeouts of a subject).
 */
class TransitionContext
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
