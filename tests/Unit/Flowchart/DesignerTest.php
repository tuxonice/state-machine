<?php

namespace Tlab\Tests\Flowchart;

use Tlab\StateMachine\Flowchart\DiagramRenderer;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\StateMachine\Flowchart\Designer;
use PHPUnit\Framework\TestCase;

class DesignerTest extends TestCase
{
    public function testFlowChartCanBeRenderer(): void
    {
        $machine = (new DefinitionReader())->read(file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.json'));
        $designer = new Designer();

        $html = $designer->renderHtml($machine);

        // The page around the graph belongs to the library and changes between its versions
        self::assertStringContainsString('<h1>State machine name</h1>', $html);
        self::assertStringContainsString(trim($designer->renderMarkdown($machine)), $html);
    }

    public function testMarkdownCanBeRenderer(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.json');
        self::assertNotFalse($definitionJson);

        $expected = <<<'GRAPHCHART'
graph TB;
    New(("New"));
    Created("Created");
    PendingPayment("PendingPayment");
    CheckPayment{"CheckPayment"};
    Cancelled(("Cancelled"));
    PaymentAuthorized("PaymentAuthorized");
    PaymentFailed("PaymentFailed");
    PreparingShipment("PreparingShipment");
    ShipmentReady("ShipmentReady");
    Invoicing("Invoicing");
    Shipped("Shipped");
    Delivered("Delivered");
    Completed(("Completed"));

    New-->|"evt:Create Order
cond:Tlab\Tests\Support\SampleCondition
cmd:Tlab\Tests\Support\SampleCommand"|Created;
    Created-->|"evt:Start Payment Process"|PendingPayment;
    PendingPayment-->|"evt:Check Payment Status"|CheckPayment;
    CheckPayment-->|"evt:Payment OK"|PaymentAuthorized;
    CheckPayment-->|"evt:Payment Not OK"|PaymentFailed;
    PaymentFailed-->|"evt:Order Cancelled"|Cancelled;
    PaymentAuthorized-->|"evt:Start Shipment Preparation"|PreparingShipment;
    PreparingShipment-->|"evt:Shipment Prepared"|ShipmentReady;
    ShipmentReady-->|"evt:Generate Invoice"|Invoicing;
    Invoicing-->|"evt:Shipment Dispatched"|Shipped;
    Shipped-->|"evt:Shipment Delivered"|Delivered;
    Delivered-->|"evt:Complete Order"|Completed;

GRAPHCHART;

        $draw = new Designer();
        self::assertEquals($expected, $draw->renderMarkdown((new DefinitionReader())->read($definitionJson)));
    }

    public function testRendersTheSameMachineTwiceWithoutAccumulating(): void
    {
        $machine = (new DefinitionReader())->read(file_get_contents(dirname(__DIR__, 2) . '/Fixtures/minimal.json'));
        $designer = new Designer();

        self::assertSame($designer->renderMarkdown($machine), $designer->renderMarkdown($machine));
    }

    public function testRunnerUsesAGivenRenderer(): void
    {
        $runner = StateMachineRunner::fromJson(file_get_contents(dirname(__DIR__, 2) . '/Fixtures/minimal.json'));
        $renderer = new class implements DiagramRenderer {
            public function renderHtml(StateMachine $machine): string
            {
                return '<html>' . $machine->getName();
            }

            public function renderMarkdown(StateMachine $machine): string
            {
                return '# ' . $machine->getName();
            }
        };

        self::assertStringStartsWith('<html>', $runner->generateHtmlDiagram($renderer));
        self::assertStringStartsWith('# ', $runner->generateMarkdownDiagram($renderer));
    }
}
