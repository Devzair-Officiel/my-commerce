<?php

namespace App\Enum;

enum CarrierType: string
{
    case Manual       = 'manual';        // Suivi manuel, pas d'API
    case Colissimo    = 'colissimo';     // API Colissimo (La Poste)
    case MondialRelay = 'mondial_relay'; // API Mondial Relay (points relais)

    public function label(): string
    {
        return match ($this) {
            self::Manual       => 'Manuel',
            self::Colissimo    => 'Colissimo',
            self::MondialRelay => 'Mondial Relay',
        };
    }

    public function supportsPickupPoints(): bool
    {
        return $this === self::MondialRelay;
    }
}
