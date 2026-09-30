<?php

namespace Tlab\Tests\Support;

use Psr\Container\ContainerInterface;

class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string,object> $services
     */
    public function __construct(private array $services)
    {
    }

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
