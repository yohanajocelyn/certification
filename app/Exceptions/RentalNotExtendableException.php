<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when an admin tries to extend a rental that does not cover today.
 */
class RentalNotExtendableException extends DomainException
{
    /**
     * Build the exception with a message the admin can read.
     */
    public static function notActiveToday(): self
    {
        return new self('Hanya sewa yang sedang berjalan hari ini yang bisa diperpanjang.');
    }
}
