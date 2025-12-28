<?php

namespace App\Enum;

enum Civility: string
{
    case MR = 'mr';
    case MRS = 'mrs';
    case MS = 'ms';

    public function label(): string
    {
        return match ($this) {
            self::MR => 'Monsieur',
            self::MRS => 'Madame',
            self::MS => 'Mademoiselle',
        };
    }
}
