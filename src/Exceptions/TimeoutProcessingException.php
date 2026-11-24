<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Exceptions;

use Tlab\StateMachine\Scheduler\FailedTimeout;
use Tlab\StateMachine\TransitionResult;

/**
 * Thrown by processTimeouts() after it has tried every due timeout, when at least one of them failed.
 * The timeouts that were applied are not undone; their results are available here.
 */
class TimeoutProcessingException extends StateMachineException
{
    /**
     * @param TransitionResult[] $results
     * @param FailedTimeout[] $failures
     */
    private function __construct(string $message, private array $results, private array $failures)
    {
        parent::__construct($message, 0, $failures[0]->getError());
    }

    /**
     * @param TransitionResult[] $results
     * @param non-empty-list<FailedTimeout> $failures
     */
    public static function withFailures(array $results, array $failures): self
    {
        $first = $failures[0];

        return new self(
            sprintf(
                '%d of %d due timeouts failed; first: event \'%s\' in state \'%s\': %s',
                count($failures),
                count($failures) + count($results),
                $first->getTimeout()->getEvent(),
                $first->getTimeout()->getState(),
                $first->getError()->getMessage()
            ),
            $results,
            $failures
        );
    }

    /**
     * The timeouts that were applied.
     *
     * @return TransitionResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return FailedTimeout[]
     */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
