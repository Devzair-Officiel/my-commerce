<?php

namespace App\Enum;

/**
 * Enumération des types de transporteurs supportés par la boutique (manuel ou Colissimo avec API La Poste).
 */
enum CarrierType: string
{
    case Manual    = 'manual';     // Suivi manuel, pas d'API
    case Colissimo = 'colissimo'; // API Colissimo (La Poste) — domicile + points relais

    public function label(): string
    {
        return match ($this) {
            self::Manual    => 'Manuel',
            self::Colissimo => 'Colissimo',
        };
    }

    public function supportsPickupPoints(): bool
    {
        return $this === self::Colissimo;
    }
}
