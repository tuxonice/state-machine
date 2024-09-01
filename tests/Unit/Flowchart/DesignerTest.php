<?php

namespace Tlab\Tests\Flowchart;

use Tlab\StateMachine\Flowchart\Designer;
use PHPUnit\Framework\TestCase;

class DesignerTest extends TestCase
{
    public function testFlowChartCanBeRenderer(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.json');

        $expected = <<<'GRAPHCHART'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>JBZoo - Mermaid Graph</title>
   <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"></script>
<script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
        window.mermaid = mermaid;
        document.dispatchEvent(new Event('MermaidLoaded'));
</script>
</head>
<body>
<h1>State machine name</h1><hr>
    <div class="mermaid" style="margin-top:20px;">graph TB;
    New("New");
    Created("Created");
    PendingPayment("PendingPayment");
    CheckPayment("CheckPayment");
    Cancelled("Cancelled");
    PaymentAuthorized("PaymentAuthorized");
    PaymentFailed("PaymentFailed");
    PreparingShipment("PreparingShipment");
    ShipmentReady("ShipmentReady");
    Invoicing("Invoicing");
    Shipped("Shipped");
    Delivered("Delivered");
    Completed("Completed");

    New-->|"evt:Create Order
cond:Tlab\StateMachine\Conditions\SampleCondition
cmd:Tlab\StateMachine\Commands\SampleCommand"|Created;
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
</div>


<script>
                $(document).on("click", "path", e => {
                    e.currentTarget.style.stroke = e.currentTarget.style.stroke ? "" : "red";
                });
            </script>
</body>
</html>
GRAPHCHART;

        $draw = new Designer();
        self::assertEquals($expected, $draw->renderGraph($definitionJson));
    }

    public function testMarkdownCanBeRenderer(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.json');

        $expected = <<<'GRAPHCHART'
graph TB;
    New("New");
    Created("Created");
    PendingPayment("PendingPayment");
    CheckPayment("CheckPayment");
    Cancelled("Cancelled");
    PaymentAuthorized("PaymentAuthorized");
    PaymentFailed("PaymentFailed");
    PreparingShipment("PreparingShipment");
    ShipmentReady("ShipmentReady");
    Invoicing("Invoicing");
    Shipped("Shipped");
    Delivered("Delivered");
    Completed("Completed");

    New-->|"evt:Create Order
cond:Tlab\StateMachine\Conditions\SampleCondition
cmd:Tlab\StateMachine\Commands\SampleCommand"|Created;
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
        self::assertEquals($expected, $draw->renderMarkdown($definitionJson));
    }
}
