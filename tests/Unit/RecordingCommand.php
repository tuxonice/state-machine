<?php

namespace Tlab\Tests;

use Tlab\StateMachine\Commands\CommandInterface;

class RecordingCommand implements CommandInterface
{
    public static int $runs = 0;

    public function run(array $data): void
    {
        self::$runs++;
    }
}
