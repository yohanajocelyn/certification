<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Services\Results\ReportTable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Garage rental reports: per month of a year, per day of a month, per garage type and per tenant.
 * Rentals count in the period of their start date, extensions in the period they were made.
 * The period's rows are loaded with Eloquent date scopes and grouped in PHP (no raw SQL).
 */
class ReportService
{
    private const MONTHS_IN_YEAR = 12;

    /** Columns added up into the totals row. */
    private const SUMMED_COLUMNS = ['rentals', 'rental_revenue', 'extensions', 'extension_revenue', 'total_revenue'];

    /**
     * One row per month of the year (January to December, empty months included).
     */
    public function monthly(int $year): ReportTable
    {
        $yearStart = CarbonImmutable::create($year)->startOfYear();
        $months = collect(range(1, self::MONTHS_IN_YEAR))
            ->mapWithKeys(fn (int $month) => [$month => $yearStart->month($month)->translatedFormat('F')]);

        return $this->table($this->buildRows(
            $months,
            Rental::query()->startingBetween($yearStart, $yearStart->endOfYear())->get(),
            RentalExtension::query()->createdBetween($yearStart, $yearStart->endOfYear())->get(),
            fn (Rental $rental) => $rental->start_date->month,
            fn (RentalExtension $extension) => $extension->created_at->month,
        ));
    }

    /**
     * One row per day of the month (28 to 31 rows, empty days included).
     *
     * @param  int  $month  1 to 12
     */
    public function daily(int $year, int $month): ReportTable
    {
        $monthStart = CarbonImmutable::create($year, $month)->startOfMonth();
        $days = collect(range(1, $monthStart->daysInMonth))
            ->mapWithKeys(fn (int $day) => [$day => $monthStart->day($day)->translatedFormat('d M Y')]);

        return $this->table($this->buildRows(
            $days,
            Rental::query()->startingBetween($monthStart, $monthStart->endOfMonth())->get(),
            RentalExtension::query()->createdBetween($monthStart, $monthStart->endOfMonth())->get(),
            fn (Rental $rental) => $rental->start_date->day,
            fn (RentalExtension $extension) => $extension->created_at->day,
        ));
    }

    /**
     * One row per garage type for the period (both ends inclusive).
     */
    public function garageTypes(CarbonInterface $from, CarbonInterface $to): ReportTable
    {
        $types = collect(GarageType::cases())->mapWithKeys(fn (GarageType $type) => [$type->value => $type->label()]);

        return $this->table($this->buildRows(
            $types,
            Rental::query()->startingBetween($from, $to)->with('garage')->get(),
            RentalExtension::query()->createdBetween($from, $to)->with('rental.garage')->get(),
            fn (Rental $rental) => $rental->garage->type->value,
            fn (RentalExtension $extension) => $extension->rental->garage->type->value,
        ));
    }

    /**
     * One row per tenant with rentals or extensions in the period, highest total revenue first.
     * A rental's tenant is its vehicle's owner.
     */
    public function tenants(CarbonInterface $from, CarbonInterface $to): ReportTable
    {
        $rentals = Rental::query()->startingBetween($from, $to)->with('vehicle.user')->get();
        $extensions = RentalExtension::query()->createdBetween($from, $to)->with('rental.vehicle.user')->get();
        $tenants = $rentals->pluck('vehicle.user')
            ->merge($extensions->pluck('rental.vehicle.user'))
            ->unique('id')
            ->mapWithKeys(fn (User $tenant) => [$tenant->id => $tenant->name.' ('.$tenant->email.')']);

        $rows = collect($this->buildRows(
            $tenants,
            $rentals,
            $extensions,
            fn (Rental $rental) => $rental->vehicle->user_id,
            fn (RentalExtension $extension) => $extension->rental->vehicle->user_id,
        ))->sortByDesc('total_revenue')->values()->all();

        return $this->table($rows);
    }

    /**
     * One row per bucket (month, day, type or tenant), in the order of the buckets given.
     *
     * @param  Collection<int|string, string>  $buckets  bucket key => label
     * @param  Collection<int, Rental>  $rentals
     * @param  Collection<int, RentalExtension>  $extensions
     * @param  callable(Rental): (int|string)  $rentalBucket
     * @param  callable(RentalExtension): (int|string)  $extensionBucket
     * @return list<array{label: string, rentals: int, rental_revenue: float, extensions: int, extension_revenue: float, total_revenue: float}>
     */
    private function buildRows(
        Collection $buckets,
        Collection $rentals,
        Collection $extensions,
        callable $rentalBucket,
        callable $extensionBucket,
    ): array {
        $rentalsByBucket = $rentals->groupBy($rentalBucket);
        $extensionsByBucket = $extensions->groupBy($extensionBucket);

        return $buckets->map(fn (string $label, int|string $bucket) => $this->row(
            $label,
            $rentalsByBucket->get($bucket, collect()),
            $extensionsByBucket->get($bucket, collect()),
        ))->values()->all();
    }

    /**
     * Counts and revenue for one bucket: rentals (rent + charging) and extensions.
     *
     * @param  Collection<int, Rental>  $rentals
     * @param  Collection<int, RentalExtension>  $extensions
     * @return array{label: string, rentals: int, rental_revenue: float, extensions: int, extension_revenue: float, total_revenue: float}
     */
    private function row(string $label, Collection $rentals, Collection $extensions): array
    {
        $rentalRevenue = round($rentals->sum(fn (Rental $rental) => (float) $rental->total_cost), 2);
        $extensionRevenue = round($extensions->sum(fn (RentalExtension $extension) => (float) $extension->additional_cost), 2);

        return [
            'label' => $label,
            'rentals' => $rentals->count(),
            'rental_revenue' => $rentalRevenue,
            'extensions' => $extensions->count(),
            'extension_revenue' => $extensionRevenue,
            'total_revenue' => round($rentalRevenue + $extensionRevenue, 2),
        ];
    }

    /**
     * Wrap the rows with their totals row.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function table(array $rows): ReportTable
    {
        $totals = ['label' => 'Total'];
        foreach (self::SUMMED_COLUMNS as $column) {
            $totals[$column] = array_sum(array_column($rows, $column));
        }

        return new ReportTable($rows, $totals);
    }
}
