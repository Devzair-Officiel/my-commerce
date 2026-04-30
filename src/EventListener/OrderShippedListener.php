<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use App\Enum\FulfillmentStatus;
use App\Message\SendShippedEmailMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Envoie automatiquement un email d'expédition dès que le fulfillmentStatus
 * d'une commande passe à Shipped.
 *
 * Utilise #[AsDoctrineListener] (listener global) pour éviter les problèmes
 * de cache de métadonnées liés à #[AsEntityListener].
 */
#[AsDoctrineListener(event: Events::preUpdate)]
final class OrderShippedListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $order = $args->getObject();

        if (!$order instanceof Order) {
            return;
        }

        if (!$args->hasChangedField('fulfillmentStatus')) {
            return;
        }

        $newValue = $args->getNewValue('fulfillmentStatus');
        $isShipped = $newValue === FulfillmentStatus::Expedie
            || $newValue === FulfillmentStatus::Expedie->value;

        if (!$isShipped) {
            return;
        }

        if ($order->isShippingEmailSent()) {
            return;
        }

        $id = $order->getId();
        if (null === $id) {
            return;
        }

        $this->bus->dispatch(new SendShippedEmailMessage($id));
    }
}
