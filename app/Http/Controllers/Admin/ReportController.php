<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Services\Results\ReportTable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Garage rental report pages: per month of a year, per day of a month, per garage type and per
 * tenant. Validates the filters; ReportService does the counting.
 */
class ReportController extends Controller
{
    private const MIN_YEAR = 2000;

    private const MAX_YEAR = 2100;

    private const DATE_FORMAT = 'Y-m-d';

    private const MONTHS_IN_YEAR = 12;

    public function __construct(private readonly ReportService $reports) {}

    /**
     * One row per month of the chosen year.
     */
    public function monthly(Request $request): View
    {
        $filters = $this->validateFilters($request, 'admin.reports.monthly', ['year' => CarbonImmutable::today()->year], [
            'year' => ['required', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR],
        ]);

        return $this->report('monthly', 'Bulan', $this->reports->monthly((int) $filters['year']), $filters);
    }

    /**
     * One row per day of the chosen month.
     */
    public function daily(Request $request): View
    {
        $today = CarbonImmutable::today();
        $filters = $this->validateFilters($request, 'admin.reports.daily', ['year' => $today->year, 'month' => $today->month], [
            'year' => ['required', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR],
            'month' => ['required', 'integer', 'between:1,'.self::MONTHS_IN_YEAR],
        ]);

        $monthOptions = collect(range(1, self::MONTHS_IN_YEAR))
            ->mapWithKeys(fn (int $month) => [$month => $today->startOfYear()->month($month)->translatedFormat('F')])
            ->all();

        return $this->report('daily', 'Tanggal', $this->reports->daily((int) $filters['year'], (int) $filters['month']), $filters)
            ->with('monthOptions', $monthOptions);
    }

    /**
     * One row per garage type for the chosen period.
     */
    public function garageTypes(Request $request): View
    {
        [$from, $to, $filters] = $this->validatePeriod($request, 'admin.reports.garage-types');

        return $this->report('garage-types', 'Tipe Garasi', $this->reports->garageTypes($from, $to), $filters);
    }

    /**
     * One row per tenant with rentals or extensions in the chosen period, highest revenue first.
     */
    public function tenants(Request $request): View
    {
        [$from, $to, $filters] = $this->validatePeriod($request, 'admin.reports.tenants');

        return $this->report('tenants', 'Penyewa', $this->reports->tenants($from, $to), $filters);
    }

    /**
     * Render a report with its rows and totals row.
     *
     * @param  array<string, mixed>  $filters
     */
    private function report(string $report, string $bucketHeading, ReportTable $table, array $filters): View
    {
        return view('admin.reports.'.$report, [
            'bucketHeading' => $bucketHeading,
            'rows' => $table->rows,
            'totals' => $table->totals,
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
}
