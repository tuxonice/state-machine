<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Flowchart;

use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;
use JBZoo\MermaidPHP\Render;
use Tlab\StateMachine\Models\State;
use Tlab\StateMachine\Models\Transition;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Exceptions\ValidationException;
use Tlab\StateMachine\Exceptions\GraphRenderException;

/**
 * Designer class for generating visual representations of state machines.
 *
 * This class provides functionality to create both HTML and Markdown
 * representations of state machines using the Mermaid graph syntax.
 */
class Designer
{
    /** @var Graph The mermaid graph instance */
    private readonly Graph $graph;

    private string $title;

    public function __construct()
    {
        $this->graph = new Graph();
    }

    /**
     * Renders the state machine as an HTML graph using Mermaid.
     *
     * @param string $jsonDefinition JSON string containing the state machine definition
     * @return string HTML representation of the state machine graph
     */
    public function renderGraph(string $jsonDefinition): string
    {
        if (empty($jsonDefinition)) {
            throw GraphRenderException::fromJsonError('JSON definition cannot be empty');
        }

        try {
            $this->buildGraph($jsonDefinition);

            return $this->graph->renderHtml([
                'theme'       => Render::THEME_DEFAULT,
                'title'       => $this->title,
                'show-zoom'   => false,
            ]);
        } catch (\Exception $e) {
            throw GraphRenderException::fromRenderError($e->getMessage());
        }
    }

    /**
     * Renders the state machine as a Markdown graph using Mermaid syntax.
     *
     * @param string $jsonDefinition JSON string containing the state machine definition
     * @return string Markdown representation of the state machine graph
     */
    public function renderMarkdown(string $jsonDefinition): string
    {
        if (empty($jsonDefinition)) {
            throw GraphRenderException::fromJsonError('JSON definition cannot be empty');
        }

        try {
            $this->buildGraph($jsonDefinition);

            return $this->graph->render();
        } catch (\Exception $e) {
            throw GraphRenderException::fromRenderError($e->getMessage());
        }
    }

    /**
     * Builds the internal graph representation from a JSON definition.
     *
     * This method reads the state machine definition, creates the graph structure,
     * and sets up all states and transitions.
     *
     * @param string $jsonDefinition JSON string containing the state machine definition
     * @throws ValidationException If the JSON definition is invalid or malformed
     */
    private function buildGraph(string $jsonDefinition): void
    {
        try {
            json_decode($jsonDefinition, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw GraphRenderException::fromJsonError($e->getMessage());
        }

        $definitionReader = new DefinitionReader();
        $machine = $definitionReader->read($jsonDefinition);

        if (empty($machine->getName())) {
            throw GraphRenderException::fromRenderError('State machine must have a name');
        }

        if (empty($machine->getStates())) {
            throw GraphRenderException::fromRenderError('State machine must have at least one state');
        }

        $nodes = $this->createStates($machine);

        $this->title = $machine->getName();

        foreach ($nodes as $node) {
            $this->graph->addNode($node);
        }

        $this->createTransitions($machine, $nodes);
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
     */
    private function createTransitions(StateMachine $machine, array $nodes): void
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

            $link = new Link($nodeFrom, $nodeTo, $linkText);
            $this->graph->addLink($link);
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
