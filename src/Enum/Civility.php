<?php

namespace App\Enum;

/**
 * Enumération des civilités utilisables dans les formulaires client (M., Mme, Mlle).
 */
enum Civility: string
{
    case MR = 'Mr';
    case MME = 'Mme';
    case MLLE = 'Mlle';

    public function label(): string
    {
        return match ($this) {
            self::MR => 'Monsieur',
            self::MME => 'Madame',
            self::MLLE => 'Mademoiselle',
        };
    }
}
