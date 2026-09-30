<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Storage;

/**
 * Implemented by objects (entities, models) that carry their own state.
 */
interface StatefulInterface
{
    /**
     * @return string|null The current state, or null when the object has none yet
     */
    public function getState(): ?string;

    public function setState(string $state): void;
}
