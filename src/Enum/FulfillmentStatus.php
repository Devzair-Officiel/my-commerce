<?php

namespace App\Enum;

enum FulfillmentStatus: string
{
    case Brouillon      = 'draft';
    case Preparation = 'preparing';
    case Expedie        = 'shipped';
    case Livre          = 'delivered';
    case Annule         = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon      => 'Brouillon',
            self::Preparation => 'Préparation',
            self::Expedie        => 'Expédiée',
            self::Livre          => 'Livrée',
            self::Annule         => 'Annulée',
        };
    }
}
