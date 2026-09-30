<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Resolver;

use Psr\Container\ContainerInterface;
use Tlab\StateMachine\Exceptions\ResolutionException;

/**
 * Resolves class names from a definition into objects.
 *
 * The PSR-11 container is asked first, so conditions and commands can have dependencies injected.
 * Without a container, or when it does not know the class, the class is instantiated without arguments.
 */
abstract class AbstractResolver
{
    public function __construct(private readonly ?ContainerInterface $container = null)
    {
    }

    /**
     * @template T of object
     * @param string $class
     * @param class-string<T> $interface
     *
     * @return T
     * @throws ResolutionException
     */
    protected function resolveInstance(string $class, string $interface): object
    {
        if ($this->container !== null && $this->container->has($class)) {
            $instance = $this->container->get($class);
        } elseif (class_exists($class)) {
            $instance = new $class();
        } else {
            throw ResolutionException::classNotFound($class);
        }

        if (!$instance instanceof $interface) {
            throw ResolutionException::wrongType($class, $interface);
        }

        return $instance;
    }
}
