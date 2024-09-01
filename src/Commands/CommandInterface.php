<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Commands;

interface CommandInterface
{
    /**
     * @param array<mixed> $data
     *
     * @return void
     */
    public function run(array $data): void;
}
