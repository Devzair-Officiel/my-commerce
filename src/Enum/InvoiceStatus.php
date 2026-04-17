<?php

namespace App\Enum;

/**
 * Enumération des statuts d'une facture (brouillon, émise, annulée).
 */
enum InvoiceStatus: string
{
    case Draft    = 'draft';
    case Issued   = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'Brouillon',
            self::Issued    => 'Émise',
            self::Cancelled => 'Annulée',
        };
    }
}
