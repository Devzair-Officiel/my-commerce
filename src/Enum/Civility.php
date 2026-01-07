<?php

namespace App\Enum;

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
