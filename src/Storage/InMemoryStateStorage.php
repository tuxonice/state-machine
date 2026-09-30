<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Storage;

use SplObjectStorage;

/**
 * Keeps states in memory, keyed by object. Useful for plain objects and tests.
 */
class InMemoryStateStorage implements StateStorageInterface
{
    /** @var SplObjectStorage<object,string> */
    private SplObjectStorage $states;

    public function __construct()
    {
        $this->states = new SplObjectStorage();
    }

    public function read(object $subject): ?string
    {
        return $this->states[$subject] ?? null;
    }

    public function write(object $subject, string $state): void
    {
        $this->states[$subject] = $state;
    }
}
