<?php

namespace Tlab\Tests\Reader;

use Tlab\StateMachine\Exceptions\ValidationException;
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
            new State('PendingPayment', true),
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

    public function testInvalidDefinitionThrowsWithSchemaErrors(): void
    {
        $definitionJson = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/invalid-sample.json');

        try {
            (new DefinitionReader())->read($definitionJson);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(
                ['/transitions/0' => 'The required properties (target) are missing'],
                $e->getErrors()
            );
        }
    }

    public function testMalformedJsonThrows(): void
    {
        $this->expectException(ValidationException::class);
        (new DefinitionReader())->read('{not json');
    }

    public function testOptionalFieldsDefaultWhenOmitted(): void
    {
        $machine = (new DefinitionReader())->read($this->fixture('minimal.json'));

        self::assertNull($machine->getEvents()[0]->getCommand());
        self::assertNull($machine->getTransitions()[0]->getCondition());
    }

    public function testIsCurrentIsRead(): void
    {
        $machine = (new DefinitionReader())->read($this->fixture('minimal.json'));

        self::assertSame('A', $machine->getCurrentState());
    }

    public function testTransitionWithoutEventIsRejectedBySchema(): void
    {
        $this->expectException(ValidationException::class);
        (new DefinitionReader())->read($this->fixture('transition-without-event.json'));
    }

    public function testReadingTwiceDoesNotAccumulateState(): void
    {
        $reader = new DefinitionReader();
        $reader->read($this->fixture('minimal.json'));
        $machine = $reader->read($this->fixture('minimal.json'));

        self::assertCount(2, $machine->getStates());
        self::assertCount(1, $machine->getTransitions());
    }

    public function testToJsonRoundTrips(): void
    {
        $reader = new DefinitionReader();
        $machine = $reader->read($this->fixture('minimal.json'));

        self::assertEquals($machine, $reader->read($machine->toJson()));
    }

    private function fixture(string $name): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/Fixtures/' . $name);
    }

    public function testReadArray(): void
    {
        $machine = (new DefinitionReader())->readArray(
            json_decode($this->fixture('minimal.json'), true)
        );

        self::assertSame('Minimal', $machine->getName());
        self::assertSame('A', $machine->getCurrentState());
    }

    public function testReadArrayValidatesLikeJson(): void
    {
        $this->expectException(ValidationException::class);
        (new DefinitionReader())->readArray(['name' => 'No states']);
    }

    public function testReadFile(): void
    {
        $machine = (new DefinitionReader())->readFile(dirname(__DIR__, 2) . '/Fixtures/minimal.json');

        self::assertSame('Minimal', $machine->getName());
    }

    public function testReadMissingFileThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be read');
        (new DefinitionReader())->readFile(dirname(__DIR__, 2) . '/Fixtures/does-not-exist.json');
    }
}
