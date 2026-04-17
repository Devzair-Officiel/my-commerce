<?php

namespace App\Enum;

/**
 * Enumération normalisée des étapes de suivi d'une expédition (créé, pris en charge, en transit, en livraison, livré, exception).
 */
enum ShipmentStatusEnum: string
{
    case CREATED = 'Création du colis';
    case PICKED_UP = 'Pris en charge par le transporteur';
    case IN_TRANSIT = 'En transit';
    case OUT_FOR_DELIVERY = 'En livraison';
    case DELIVERED = 'Livré';
    case EXCEPTION = 'Exception';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Création du colis',
            self::PICKED_UP => 'Pris en charge par le transporteur',
            self::IN_TRANSIT => 'En transit',
            self::OUT_FOR_DELIVERY => 'En livraison',
            self::DELIVERED => 'Livré',
            self::EXCEPTION => 'Exception',
        };
    }
}
