<?php

namespace Tlab\Tests\Reader;

use Tlab\StateMachine\Reader\DefinitionReader;
use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Models\Event;
use Tlab\StateMachine\Models\State;
use Tlab\StateMachine\Models\Transition;

class DefinitionReaderTest extends TestCase
{
    public function testRead(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.json');

        $definitionReader = new DefinitionReader();
        $machine = $definitionReader->read($definitionJson);

        self::assertEquals([
            new State('New'),
            new State('Created'),
            new State('PendingPayment'),
            new State('CheckPayment'),
            new State('Cancelled'),
            new State('PaymentAuthorized'),
            new State('PaymentFailed'),
            new State('PreparingShipment'),
            new State('ShipmentReady'),
            new State('Invoicing'),
            new State('Shipped'),
            new State('Delivered'),
            new State('Completed'),
        ], $machine->getStates());
        self::assertEquals([
            Transition::createFromArray([
                'source' => 'New',
                'target' => 'Created',
                'event' => 'Create Order',
                'condition' => 'Tlab\\StateMachine\\Conditions\\SampleCondition',
            ]),
            Transition::createFromArray([
                'source' => 'Created',
                'target' => 'PendingPayment',
                'event' => 'Start Payment Process',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'PendingPayment',
                'target' => 'CheckPayment',
                'event' => 'Check Payment Status',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'CheckPayment',
                'target' => 'PaymentAuthorized',
                'event' => 'Payment OK',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'CheckPayment',
                'target' => 'PaymentFailed',
                'event' => 'Payment Not OK',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'PaymentFailed',
                'target' => 'Cancelled',
                'event' => 'Order Cancelled',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'PaymentAuthorized',
                'target' => 'PreparingShipment',
                'event' => 'Start Shipment Preparation',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'PreparingShipment',
                'target' => 'ShipmentReady',
                'event' => 'Shipment Prepared',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'ShipmentReady',
                'target' => 'Invoicing',
                'event' => 'Generate Invoice',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'Invoicing',
                'target' => 'Shipped',
                'event' => 'Shipment Dispatched',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'Shipped',
                'target' => 'Delivered',
                'event' => 'Shipment Delivered',
                'condition' => null,
            ]),
            Transition::createFromArray([
                'source' => 'Delivered',
                'target' => 'Completed',
                'event' => 'Complete Order',
                'condition' => null,
            ]),
        ], $machine->getTransitions());
        self::assertEquals([
            Event::createFromArray([
                'name' => 'Create Order',
                'command' => 'Tlab\\StateMachine\\Commands\\SampleCommand'
            ]),
            Event::createFromArray([
                'name' => 'Start Payment Process',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Check Payment Status',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Payment OK',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Payment Not OK',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Order Cancelled',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Start Shipment Preparation',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Shipment Prepared',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Generate Invoice',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Shipment Dispatched',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Shipment Delivered',
                'command' => null
            ]),
            Event::createFromArray([
                'name' => 'Complete Order',
                'command' => null
            ])
        ], $machine->getEvents());
    }
}
