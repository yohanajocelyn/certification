<?php

namespace App\Exceptions;

use App\Models\User;
use DomainException;

/**
 * Thrown when an admin tries to accept or reject a registration that was already decided.
 */
class TenantAlreadyDecidedException extends DomainException
{
    /**
     * Build the exception with a message the admin can read.
     */
    public static function for(User $tenant): self
    {
        return new self("Pendaftaran {$tenant->name} sudah diputuskan sebelumnya.");
    }
}
