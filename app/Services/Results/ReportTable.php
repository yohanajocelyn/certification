<?php

namespace App\Services\Results;

/**
 * One rental report: a row per bucket (month, day, garage type or tenant) and a totals row.
 *
 * Every row: label, rentals, rental_revenue, extensions, extension_revenue, total_revenue.
 */
final readonly class ReportTable
{
    /**
     * @param  list<array{label: string, rentals: int, rental_revenue: float, extensions: int, extension_revenue: float, total_revenue: float}>  $rows
     * @param  array{label: string, rentals: int, rental_revenue: float, extensions: int, extension_revenue: float, total_revenue: float}  $totals
     */
    public function __construct(
        public array $rows,
        public array $totals,
    ) {}
}
