<?php

namespace App\Enum;

enum UserRole: string
{
    case User       = 'ROLE_USER';
    case Admin      = 'ROLE_ADMIN';
    case SuperAdmin = 'ROLE_SUPER_ADMIN';

    public function label(): string
    {
        return match($this) {
            self::User       => 'Utilisateur (client)',
            self::Admin      => 'Administrateur',
            self::SuperAdmin => 'Super administrateur',
        };
    }

    /** Retourne les rôles effectifs incluant la hiérarchie */
    public function impliedRoles(): array
    {
        return match($this) {
            self::User       => ['ROLE_USER'],
            self::Admin      => ['ROLE_ADMIN', 'ROLE_USER'],
            self::SuperAdmin => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'],
        };
    }
}
