<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Flowchart;

use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;
use JBZoo\MermaidPHP\Render;
use Tlab\StateMachine\Models\State;
use Tlab\StateMachine\Models\Transition;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Exceptions\GraphRenderException;

/**
 * Renders state machines with Mermaid, through the optional jbzoo/mermaid-php package.
 */
class Designer implements DiagramRenderer
{
    /**
     * @throws GraphRenderException when jbzoo/mermaid-php is not installed
     */
    public function __construct()
    {
        if (!class_exists(Graph::class)) {
            throw GraphRenderException::missingDependency('jbzoo/mermaid-php');
        }
    }

    public function renderHtml(StateMachine $machine): string
    {
        try {
            return $this->buildGraph($machine)->renderHtml([
                'theme'       => Render::THEME_DEFAULT,
                'title'       => $machine->getName(),
                'show-zoom'   => '0',
            ]);
        } catch (\Exception $e) {
            throw GraphRenderException::fromRenderError($e->getMessage());
        }
    }

    public function renderMarkdown(StateMachine $machine): string
    {
        try {
            return $this->buildGraph($machine)->render();
        } catch (\Exception $e) {
            throw GraphRenderException::fromRenderError($e->getMessage());
        }
    }

    /**
     * Builds a fresh graph with a node per state and a link per transition.
     */
    private function buildGraph(StateMachine $machine): Graph
    {
        $graph = new Graph();
        $nodes = $this->createStates($machine);

        foreach ($nodes as $node) {
            $graph->addNode($node);
        }

        $this->createTransitions($machine, $nodes, $graph);

        return $graph;
    }

    /**
     * Creates Node objects for all states in the state machine.
     *
     * This method processes each state in the machine and creates a corresponding
     * visual node with appropriate styling based on the state type (start, end, etc).
     *
     * @param StateMachine $machine The state machine instance
     * @return array<string, Node> Array of nodes indexed by state name
     */
    private function createStates(StateMachine $machine): array
    {
        $nodes = [];
        $transitions = $machine->getTransitions();
        $states = $machine->getStates();
        foreach ($states as $state) {
            $stateName = $state->getName();
            $nodeType = $this->getStateNodeType($state, $transitions);
            $node = new Node($stateName, $stateName, $nodeType);
            $nodes[$stateName] = $node;
        }

        return $nodes;
    }

    /**
     * Creates the transitions between states in the graph.
     *
     * This method processes all transitions in the state machine and creates
     * visual connections between the corresponding nodes, including labels
     * for events and conditions.
     *
     * @param StateMachine $machine The state machine instance
     * @param array<string, Node> $nodes Array of nodes indexed by state name
     * @param Graph $graph The graph to add the links to
     */
    private function createTransitions(StateMachine $machine, array $nodes, Graph $graph): void
    {
        $transitions = $machine->getTransitions();

        $eventsList = [];
        foreach ($machine->getEvents() as $event) {
            $eventsList[$event->getName()] = $event;
        }

        foreach ($transitions as $transition) {
            $nodeFrom = $nodes[$transition->getSource()];
            $nodeTo = $nodes[$transition->getTarget()];

            $linkText = 'evt:' . $transition->getEvent();
            if ($transition->getCondition()) {
                $linkText .= "\ncond:" . $transition->getCondition();
            }

            if ($eventsList[$transition->getEvent()]->getCommand()) {
                $linkText .= "\ncmd:" . $eventsList[$transition->getEvent()]->getCommand();
            }

            if ($transition->getCommand()) {
                $linkText .= "\ncmd:" . $transition->getCommand();
            }

            $link = new Link($nodeFrom, $nodeTo, $linkText);
            $graph->addLink($link);
        }
    }

    /**
     * @param State $state
     * @param array<Transition> $transitions
     * @return string
     */
    private function getStateNodeType(State $state, array $transitions): string
    {
        $stateName = $state->getName();
        $inCount = 0;
        $outCount = 0;
        foreach ($transitions as $transition) {
            if ($stateName === $transition->getTarget()) {
                $inCount++;
            }

            if ($stateName === $transition->getSource()) {
                $outCount++;
            }
        }

        if ($outCount === 0 || $inCount === 0) {
            return Node::CIRCLE;
        }

        if ($outCount > 1) {
            return Node::RHOMBUS;
        }

        return Node::ROUND;
    }
}
