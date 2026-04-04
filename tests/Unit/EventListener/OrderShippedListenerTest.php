<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Order;
use App\Enum\FulfillmentStatus;
use App\EventListener\OrderShippedListener;
use App\Message\SendShippedEmailMessage;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Ce que l'on teste :
 *  - Entité autre qu'Order → pas de dispatch
 *  - Champ fulfillmentStatus non modifié → pas de dispatch
 *  - Nouveau statut != Shipped → pas de dispatch
 *  - Nouveau statut = Shipped (instance enum) → dispatch
 *  - Nouveau statut = 'shipped' (backing value string) → dispatch
 *  - Order sans ID → pas de dispatch
 */
class OrderShippedListenerTest extends TestCase
{
    private function makeArgs(object $entity, bool $fieldChanged, mixed $newValue): PreUpdateEventArgs
    {
        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($entity);
        $args->method('hasChangedField')->willReturn($fieldChanged);
        $args->method('getNewValue')->willReturn($newValue);

        return $args;
    }

    private function makeOrder(?int $id): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn($id);

        return $order;
    }

    public function testIgnoresNonOrderEntities(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $args = $this->makeArgs(new \stdClass(), false, null);

        (new OrderShippedListener($bus))->preUpdate($args);
    }

    public function testIgnoresWhenFulfillmentStatusDidNotChange(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $args = $this->makeArgs($this->makeOrder(1), false, null);

        (new OrderShippedListener($bus))->preUpdate($args);
    }

    public function testIgnoresWhenNewStatusIsNotShipped(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $args = $this->makeArgs($this->makeOrder(1), true, FulfillmentStatus::Preparing);

        (new OrderShippedListener($bus))->preUpdate($args);
    }

    public function testIgnoresWhenNewStatusStringIsNotShipped(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $args = $this->makeArgs($this->makeOrder(1), true, 'preparing');

        (new OrderShippedListener($bus))->preUpdate($args);
    }

    public function testIgnoresWhenOrderHasNoId(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $args = $this->makeArgs($this->makeOrder(null), true, FulfillmentStatus::Shipped);

        (new OrderShippedListener($bus))->preUpdate($args);
    }

    public function testDispatchesWhenStatusChangesToShippedEnum(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendShippedEmailMessage::class))
            ->willReturn(new Envelope(new SendShippedEmailMessage(42)));

        $args = $this->makeArgs($this->makeOrder(42), true, FulfillmentStatus::Shipped);

        (new OrderShippedListener($bus))->preUpdate($args);
    }

    public function testDispatchesWhenStatusChangesToShippedString(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendShippedEmailMessage::class))
            ->willReturn(new Envelope(new SendShippedEmailMessage(7)));

        $args = $this->makeArgs($this->makeOrder(7), true, 'shipped');

        (new OrderShippedListener($bus))->preUpdate($args);
    }
}
