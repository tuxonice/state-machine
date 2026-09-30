<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Storage;

/**
 * Reads and writes the state of a subject. Implement it to persist state wherever it lives,
 * e.g. to save an entity after it changes.
 */
interface StateStorageInterface
{
    /**
     * @return string|null The current state, or null when the subject has none yet
     */
    public function read(object $subject): ?string;

    public function write(object $subject, string $state): void;
}
