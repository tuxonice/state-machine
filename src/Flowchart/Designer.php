<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Flowchart;

use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;
use JBZoo\MermaidPHP\Render;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Models\StateMachine;

class Designer
{
    private Graph $graph;

    private string $title;

    public function renderGraph(string $jsonDefinition): string
    {
        $this->buildGraph($jsonDefinition);

        return $this->graph->renderHtml([
            'theme'       => Render::THEME_DEFAULT,
            'title'       => $this->title,
            'show-zoom'   => false,
        ]);
    }

    /**
     * @param string $jsonDefinition
     *
     * @return string
     * @throws \Tlab\StateMachine\Exceptions\ValidationException
     */
    public function renderMarkdown(string $jsonDefinition): string
    {
        $this->buildGraph($jsonDefinition);

        return $this->graph->render();
    }

    /**
     * @param string $jsonDefinition
     *
     * @return void
     * @throws \Tlab\StateMachine\Exceptions\ValidationException
     */
    private function buildGraph(string $jsonDefinition): void
    {
        $definitionReader = new DefinitionReader();
        $machine = $definitionReader->read($jsonDefinition);
        $this->title = $machine->getName();
        $this->graph = new Graph();

        $nodes = $this->createStates($machine);

        foreach ($nodes as $node) {
            $this->graph->addNode($node);
        }

        $this->createTransitions($machine, $nodes);
    }

    /**
     * @param \Tlab\StateMachine\Models\StateMachine $machine
     *
     * @return array<Node>
     */
    private function createStates(StateMachine $machine): array
    {
        $nodes = [];
        $states = $machine->getStates();
        foreach ($states as $state) {
            $stateName = $state->getName();
            $nodes[$stateName] = new Node($stateName, $stateName);
        }

        return $nodes;
    }

    /**
     * @param StateMachine $machine
     * @param array<\JBZoo\MermaidPHP\Node> $nodes
     *
     * @return void
     */
    private function createTransitions(StateMachine $machine, array $nodes): void
    {
        $transitions = $machine->getTransitions();

        $eventsList = [];
        foreach ($machine->getEvents() as $event) {
            $eventsList[$event->getName()] = $event;
        }

        foreach ($transitions as $transition) {
            $nodeFrom = $nodes[$transition->getFrom()];
            $nodeTo = $nodes[$transition->getTo()];

            $linkText = 'evt:' . $transition->getEvent();
            if ($transition->getCondition()) {
                $linkText .= "\ncond:" . $transition->getCondition();
            }

            if ($eventsList[$transition->getEvent()]->getCommand()) {
                $linkText .= "\ncmd:" . $eventsList[$transition->getEvent()]->getCommand();
            }

            $link = new Link($nodeFrom, $nodeTo, $linkText);
            $this->graph->addLink($link);
        }
    }
}
