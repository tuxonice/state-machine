<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Scheduler;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Turns an event timeout such as "30 minutes" or "1 day" into a due date.
 */
final class TimeoutParser
{
    public const PATTERN = '/^\s*(\d+)\s*(second|minute|hour|day|week|month|year)s?\s*$/';

    public static function isValid(string $timeout): bool
    {
        return preg_match(self::PATTERN, $timeout) === 1;
    }

    public static function dueAt(DateTimeImmutable $from, string $timeout): DateTimeImmutable
    {
        if (preg_match(self::PATTERN, $timeout, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid timeout '{$timeout}'");
        }

        return $from->modify("+{$matches[1]} {$matches[2]}");
    }
}
