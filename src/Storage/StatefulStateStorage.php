<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Storage;

use InvalidArgumentException;

/**
 * Default storage: the subject keeps its own state through StatefulInterface.
 */
class StatefulStateStorage implements StateStorageInterface
{
    public function read(object $subject): ?string
    {
        return $this->stateful($subject)->getState();
    }

    public function write(object $subject, string $state): void
    {
        $this->stateful($subject)->setState($state);
    }

    private function stateful(object $subject): StatefulInterface
    {
        if (!$subject instanceof StatefulInterface) {
            throw new InvalidArgumentException(
                sprintf('%s does not implement %s', $subject::class, StatefulInterface::class)
            );
        }

        return $subject;
    }
}
