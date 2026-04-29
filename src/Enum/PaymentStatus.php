<?php

namespace App\Enum;

/**
 * Enumération des statuts de paiement d'une commande (en attente, payé, remboursé, échec).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'En attente',
            self::Paid     => 'Payé',
            self::Refunded => 'Remboursé',
            self::Failed   => 'Échec',
            self::Disputed => 'Contesté',
        };
    }
}
