<?php

namespace Tlab\Tests\Support;

use Tlab\StateMachine\Commands\CommandInterface;

class LogCommand implements CommandInterface
{
    /** @var string[] */
    public static array $log = [];

    public function run(array $data): void
    {
        self::$log[] = 'run';
    }
}
