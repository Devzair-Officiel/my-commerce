<?php

namespace App\Enum;

/**
 * Enumération des statuts de traitement (fulfillment) d'une commande (brouillon, préparation, expédiée, livrée, annulée).
 */
enum FulfillmentStatus: string
{
    case Draft = 'draft';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Preparing => 'Préparation',
            self::Shipped => 'Expédiée',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
        };
    }
}
