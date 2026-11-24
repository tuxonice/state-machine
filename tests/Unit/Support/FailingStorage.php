<?php

namespace Tlab\Tests\Support;

use RuntimeException;
use Tlab\StateMachine\Storage\StatefulStateStorage;
use Tlab\StateMachine\Storage\StateStorageInterface;

/**
 * Stores state on the subject, but writing one chosen subject fails, like a row that keeps hitting a database error.
 */
class FailingStorage implements StateStorageInterface
{
    private StatefulStateStorage $inner;

    public ?object $poison;

    public function __construct(?object $poison)
    {
        $this->inner = new StatefulStateStorage();
        $this->poison = $poison;
    }

    public function read(object $subject): ?string
    {
        return $this->inner->read($subject);
    }

    public function write(object $subject, string $state): void
    {
        if ($subject === $this->poison) {
            throw new RuntimeException('write failed');
        }

        $this->inner->write($subject, $state);
    }
}
