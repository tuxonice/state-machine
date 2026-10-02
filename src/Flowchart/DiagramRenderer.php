<?php

declare(strict_types=1);

namespace Tlab\StateMachine\Flowchart;

use Tlab\StateMachine\Models\StateMachine;

/**
 * Draws a state machine. Implement it to use a diagram library other than Mermaid.
 */
interface DiagramRenderer
{
    public function renderHtml(StateMachine $machine): string;

    public function renderMarkdown(StateMachine $machine): string;
}
