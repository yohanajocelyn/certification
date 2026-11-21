<?php

namespace App\Enums;

/**
 * The two kinds of account in the app.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Tenant = 'tenant';

    /**
     * Indonesian name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Tenant => 'Penyewa',
        };
    }
}
