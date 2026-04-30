<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case Attente = 'pending';
    case Paye      = 'paid';
    case Rembourse = 'refunded';
    case Echoue    = 'failed';
    case Conteste  = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Attente => 'En attente',
            self::Paye      => 'Payé',
            self::Rembourse => 'Remboursé',
            self::Echoue    => 'Échec',
            self::Conteste  => 'Contesté',
        };
    }
}
