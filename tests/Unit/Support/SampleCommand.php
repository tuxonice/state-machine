<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Commands\CommandInterface;

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
