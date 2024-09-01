<?php

use Tlab\StateMachine\Flowchart\Designer;

ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

$jsonDefinition = file_get_contents(dirname(__DIR__).'/src/Machines/sample.json');

echo((new Designer())->renderGraph($jsonDefinition));

