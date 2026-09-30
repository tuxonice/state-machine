<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Resolver;

use Tlab\StateMachine\Commands\CommandInterface;

class CommandResolver extends AbstractResolver implements CommandResolverInterface
{
    public function resolve(string $class): CommandInterface
    {
        return $this->resolveInstance($class, CommandInterface::class);
    }
}
