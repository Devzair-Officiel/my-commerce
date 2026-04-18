<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use App\Message\SendBackInStockAlertMessage;
use App\Repository\StockAlertRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatch une alerte retour en stock quand le stock d'un produit passe de 0 à >0.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
final class ProductStockListener
{
    public function __construct(
        private readonly MessageBusInterface  $bus,
        private readonly StockAlertRepository $stockAlertRepository,
    ) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $product = $args->getObject();

        if (!$product instanceof Product) {
            return;
        }

        if (!$args->hasChangedField('stock')) {
            return;
        }

        $oldStock = $args->getOldValue('stock');
        $newStock = $args->getNewValue('stock');

        // Déclenche uniquement quand le stock passe de épuisé (0 ou null) à disponible (>0)
        $wasEmpty = $oldStock === null || $oldStock === 0;
        $isNowAvailable = $newStock !== null && $newStock > 0;

        if (!$wasEmpty || !$isNowAvailable) {
            return;
        }

        // Ne dispatch que s'il y a des abonnés en attente
        if ($this->stockAlertRepository->countByProduct($product) === 0) {
            return;
        }

        $id = $product->getId();
        if (null === $id) {
            return;
        }

        $this->bus->dispatch(new SendBackInStockAlertMessage($id));
    }
}
