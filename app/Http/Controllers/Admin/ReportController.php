<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Garage rental reports: per month of a year, per day of a month, per garage type and per tenant.
 * Rentals count in the period of their start date, extensions in the period they were made.
 */
class ReportController extends Controller
{
    private const MIN_YEAR = 2000;

    private const MAX_YEAR = 2100;

    private const DATE_FORMAT = 'Y-m-d';

    /**
     * One row per month of the chosen year.
     */
    public function monthly(Request $request): View
    {
        $today = CarbonImmutable::today();
        $filters = $this->validateFilters($request, 'admin.reports.monthly', ['year' => $today->year], [
            'year' => ['required', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR],
        ]);
        $yearStart = CarbonImmutable::create((int) $filters['year'])->startOfYear();

        $months = collect(range(1, 12))->mapWithKeys(fn (int $month) => [$month => $yearStart->month($month)->translatedFormat('F')]);
        $rows = $this->buildRows(
            $months,
            Rental::query()->startingBetween($yearStart, $yearStart->endOfYear())->get(),
            RentalExtension::query()->createdBetween($yearStart, $yearStart->endOfYear())->get(),
            fn (Rental $rental) => $rental->start_date->month,
            fn (RentalExtension $extension) => $extension->created_at->month,
        );

        return $this->report('monthly', 'Bulan', 'Tahun '.$yearStart->year, $rows, $filters);
    }

    /**
     * One row per day of the chosen month.
     */
    public function daily(Request $request): View
    {
        $today = CarbonImmutable::today();
        $filters = $this->validateFilters($request, 'admin.reports.daily', ['year' => $today->year, 'month' => $today->month], [
            'year' => ['required', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);
        $monthStart = CarbonImmutable::create((int) $filters['year'], (int) $filters['month'])->startOfMonth();

        $days = collect(range(1, $monthStart->daysInMonth))->mapWithKeys(fn (int $day) => [$day => $monthStart->day($day)->translatedFormat('d M Y')]);
        $rows = $this->buildRows(
            $days,
            Rental::query()->startingBetween($monthStart, $monthStart->endOfMonth())->get(),
            RentalExtension::query()->createdBetween($monthStart, $monthStart->endOfMonth())->get(),
            fn (Rental $rental) => $rental->start_date->day,
            fn (RentalExtension $extension) => $extension->created_at->day,
        );

        $monthOptions = collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => $monthStart->month($month)->translatedFormat('F')])
            ->all();

        return $this->report('daily', 'Tanggal', $monthStart->translatedFormat('F Y'), $rows, $filters)
            ->with('monthOptions', $monthOptions);
    }

    /**
     * One row per garage type for the chosen period.
     */
    public function garageTypes(Request $request): View
    {
        [$from, $to, $filters] = $this->validatePeriod($request, 'admin.reports.garage-types');

        $types = collect(GarageType::cases())->mapWithKeys(fn (GarageType $type) => [$type->value => $type->label()]);
        $rows = $this->buildRows(
            $types,
            Rental::query()->startingBetween($from, $to)->with('garage')->get(),
            RentalExtension::query()->createdBetween($from, $to)->with('rental.garage')->get(),
            fn (Rental $rental) => $rental->garage->type->value,
            fn (RentalExtension $extension) => $extension->rental->garage->type->value,
        );

        return $this->report('garage-types', 'Tipe Garasi', $this->periodLabel($from, $to), $rows, $filters);
    }

    /**
     * One row per tenant with rentals or extensions in the chosen period, highest revenue first.
     */
    public function tenants(Request $request): View
    {
        [$from, $to, $filters] = $this->validatePeriod($request, 'admin.reports.tenants');

        $rentals = Rental::query()->startingBetween($from, $to)->with('user')->get();
        $extensions = RentalExtension::query()->createdBetween($from, $to)->with('rental.user')->get();
        $tenants = $rentals->pluck('user')
            ->merge($extensions->pluck('rental.user'))
            ->unique('id')
            ->mapWithKeys(fn (User $tenant) => [$tenant->id => $tenant->name.' ('.$tenant->email.')']);

        $rows = collect($this->buildRows(
            $tenants,
            $rentals,
            $extensions,
            fn (Rental $rental) => $rental->user_id,
            fn (RentalExtension $extension) => $extension->rental->user_id,
        ))->sortByDesc('total_revenue')->values()->all();

        return $this->report('tenants', 'Penyewa', $this->periodLabel($from, $to), $rows, $filters);
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
     * Render a report with its rows and a totals row.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function report(string $report, string $bucketHeading, string $periodLabel, array $rows, array $filters): View
    {
        $totals = ['label' => 'Total'];
        foreach (['rentals', 'rental_revenue', 'extensions', 'extension_revenue', 'total_revenue'] as $column) {
            $totals[$column] = array_sum(array_column($rows, $column));
        }

        return view('admin.reports.'.$report, [
            'bucketHeading' => $bucketHeading,
            'periodLabel' => $periodLabel,
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
        ]);
    }

    /**
     * Validate a from/to date range, defaulting to the current month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array<string, mixed>}
     */
    private function validatePeriod(Request $request, string $routeName): array
    {
        $today = CarbonImmutable::today();
        $filters = $this->validateFilters($request, $routeName, [
            'from' => $today->startOfMonth()->toDateString(),
            'to' => $today->endOfMonth()->toDateString(),
        ], [
            'from' => ['required', 'date_format:'.self::DATE_FORMAT],
            'to' => ['required', 'date_format:'.self::DATE_FORMAT, 'after_or_equal:from'],
        ]);

        return [
            CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $filters['from']),
            CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $filters['to']),
            $filters,
        ];
    }

    /**
     * Fill missing filters with defaults and validate them. On failure go to the report without
     * filters (never "back", which could be this same failing page).
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateFilters(Request $request, string $routeName, array $defaults, array $rules): array
    {
        $request->mergeIfMissing($defaults);

        try {
            return $request->validate($rules);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route($routeName));
        }
    }

    /**
     * Readable period, e.g. "01 Nov 2026 – 30 Nov 2026".
     */
    private function periodLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y');
    }
}
