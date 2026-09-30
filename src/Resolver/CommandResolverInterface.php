<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Resolver;

use Tlab\StateMachine\Commands\CommandInterface;
use Tlab\StateMachine\Exceptions\ResolutionException;

interface CommandResolverInterface
{
    /**
     * @param class-string $class
     *
     * @throws ResolutionException
     */
    public function resolve(string $class): CommandInterface;
}
