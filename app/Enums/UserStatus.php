<?php

namespace App\Enums;

/**
 * Verification state of a tenant registration.
 */
enum UserStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Indonesian name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Approved => 'Diterima',
            self::Rejected => 'Ditolak',
        };
    }
}
