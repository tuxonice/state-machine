<?php

// Prints a Mermaid diagram of an example machine. Needs the optional jbzoo/mermaid-php package.
//
//   php examples/diagram.php [examples/realestate.json] > diagram.html

use Tlab\StateMachine\StateMachineRunner;

require __DIR__ . '/../vendor/autoload.php';

$file = $argv[1] ?? __DIR__ . '/realestate.json';
$runner = StateMachineRunner::fromJson((string) file_get_contents($file));

echo $runner->generateHtmlDiagram();
