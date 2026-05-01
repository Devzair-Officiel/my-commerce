<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case Create = 'CREATE';
    case Update = 'UPDATE';
    case Delete = 'DELETE';

    public function label(): string
    {
        return match($this) {
            self::Create => 'Création',
            self::Update => 'Modification',
            self::Delete => 'Suppression',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::Create => 'success',
            self::Update => 'warning',
            self::Delete => 'danger',
        };
    }
}
