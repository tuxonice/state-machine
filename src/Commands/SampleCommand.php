<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Commands;

class SampleCommand implements CommandInterface
{
    /**
     * @param array<mixed> $data
     *
     * @return void
     */
    public function run(array $data): void
    {
        // eg: Send notification by email
    }
}
