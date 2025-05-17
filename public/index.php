<?php

use Tlab\StateMachine\StateMachineRunner;

ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

$jsonDefinition = file_get_contents(dirname(__DIR__).'/src/Machines/realestate.json');

$stateMachineRunner = new StateMachineRunner($jsonDefinition);
dd($stateMachineRunner->getStateMachine());
echo($stateMachineRunner->generateMarkdownDiagram());

