<?php

namespace Tlab\Tests\Exceptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Exceptions\GraphRenderException;
use Tlab\StateMachine\Exceptions\ResolutionException;
use Tlab\StateMachine\Exceptions\StateMachineException;
use Tlab\StateMachine\Exceptions\UnknownEventException;
use Tlab\StateMachine\Exceptions\UnknownStateException;
use Tlab\StateMachine\Exceptions\ValidationException;

class StateMachineExceptionTest extends TestCase
{
    /**
     * @return array<string,array{class-string<\Throwable>}>
     */
    public static function packageExceptions(): array
    {
        return [
            'validation' => [ValidationException::class],
            'unknown state' => [UnknownStateException::class],
            'unknown event' => [UnknownEventException::class],
            'resolution' => [ResolutionException::class],
            'graph render' => [GraphRenderException::class],
        ];
    }

    /**
     * @param class-string<\Throwable> $class
     */
    #[DataProvider('packageExceptions')]
    public function testAllPackageExceptionsShareTheBase(string $class): void
    {
        self::assertTrue(is_subclass_of($class, StateMachineException::class));
    }
}
