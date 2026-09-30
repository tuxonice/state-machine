<?php

namespace Tlab\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Storage\InMemoryStateStorage;
use Tlab\StateMachine\Storage\StatefulStateStorage;
use Tlab\Tests\Support\Order;

class StateStorageTest extends TestCase
{
    public function testInMemoryStorageKeepsStatePerObject(): void
    {
        $storage = new InMemoryStateStorage();
        $a = new \stdClass();
        $b = new \stdClass();

        self::assertNull($storage->read($a));

        $storage->write($a, 'S1');
        $storage->write($b, 'S2');

        self::assertSame('S1', $storage->read($a));
        self::assertSame('S2', $storage->read($b));
    }

    public function testStatefulStorageDelegatesToTheSubject(): void
    {
        $storage = new StatefulStateStorage();
        $order = new Order('S1');

        self::assertSame('S1', $storage->read($order));

        $storage->write($order, 'S2');

        self::assertSame('S2', $order->getState());
    }

    public function testStatefulStorageRejectsObjectsThatAreNotStateful(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new StatefulStateStorage())->read(new \stdClass());
    }
}
