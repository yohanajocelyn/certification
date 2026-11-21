<?php

namespace App\Http\Controllers;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\RentalService;
use App\Services\VehicleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tenant pages: my rentals, and renting a garage in two steps
 * (1. vehicle and dates, 2. garage, total and payment proof).
 */
class RentalController extends Controller
{
    public function __construct(
        private readonly RentalService $rentals,
        private readonly VehicleService $vehicles,
    ) {}

    /**
     * List the tenant's own rentals, newest start first.
     */
    public function index(Request $request): View
    {
        return view('rentals.index', [
            'rentals' => $this->rentals->rentalsOf($request->user()),
            'today' => CarbonImmutable::today(),
        ]);
    }

    /**
     * Step 1: choose a vehicle, rental type, start date and number of days.
     * Query values (from the "change" link on step 2) refill the form.
     */
    public function create(Request $request): View
    {
        $vehicles = $this->vehicles->allForTenant($request->user());
        $typesWhere = fn (callable $rule) => array_values(array_map(
            fn (VehicleType $type) => $type->value,
            array_filter(VehicleType::cases(), fn (VehicleType $type) => $rule(Vehicle::forType($type))),
        ));

        return view('rentals.create', [
            'vehicles' => $vehicles,
            'vehicleTypes' => VehicleType::cases(),
            'plateVehicleTypes' => $typesWhere(fn (Vehicle $vehicle) => $vehicle->requiresPlate()),
            'batteryVehicleTypes' => $typesWhere(fn (Vehicle $vehicle) => $vehicle->hasBattery()),
            'electricVehicleIds' => $vehicles->filter(fn (Vehicle $vehicle) => $vehicle->hasBattery())->pluck('id')->all(),
            'rentalTypes' => RentalType::cases(),
            'today' => CarbonImmutable::today(),
            'startDate' => $this->requestedStartDate($request),
            'initialEndDate' => $this->requestedEndDate($request),
            'defaults' => $request->query(),
        ]);
    }

    /**
     * Step 2: show the end date, the garages free for the whole period and each one's total.
     */
    public function preview(Request $request): View
    {
        try {
            $details = $this->validateRentalDetails($request);
            $quote = $this->rentals->quote($request->user(), $details);
        } catch (ValidationException $exception) {
            // Always back to step 1: "back" could be this same page and loop when saving failed.
            throw $exception->redirectTo(route('rentals.create'));
        }

        return view('rentals.preview', ['details' => $details, ...$quote]);
    }

    /**
     * Save the rental with the chosen garage and the payment proof.
     */
    public function store(Request $request): RedirectResponse
    {
        $details = $this->validateRentalDetails($request);
        $choice = $request->validate([
            'garage_type' => ['required', Rule::enum(GarageType::class)],
            'garage_id' => ['required', 'integer', Rule::exists('garages', 'id')->where('type', $request->input('garage_type'))],
            'payment_proof' => ['required', 'image', 'mimes:'.implode(',', Rental::PAYMENT_PROOF_MIMES), 'max:'.Rental::PAYMENT_PROOF_MAX_KB],
        ]);

        $rental = $this->rentals->book($request->user(), $details, (int) $choice['garage_id'], $choice['payment_proof']);

        return redirect()->route('rentals.index')
            ->with('success', "Sewa garasi {$rental->garage->code} berhasil disimpan.");
    }

    /**
     * Validate step 1 (sent again with step 2). Plates are cleaned up first, so "b  1234 xyz"
     * passes and is checked for duplicates as "B 1234 XYZ".
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateRentalDetails(Request $request): array
    {
        if ($request->has('plate_number')) {
            $request->merge(['plate_number' => Vehicle::normalizePlate($request->input('plate_number'))]);
        }

        return $request->validate($this->rentalDetailsRules($request, $this->vehicles->allForTenant($request->user())));
    }

    /**
     * Step 1 rules. Fields that do not apply (plate of a bicycle, battery of a petrol car,
     * days of a monthly rental, new-vehicle fields for an existing vehicle) are dropped.
     *
     * @param  Collection<int, Vehicle>  $ownVehicles
     * @return array<string, mixed>
     */
    private function rentalDetailsRules(Request $request, Collection $ownVehicles): array
    {
        $ownVehicles = $ownVehicles->keyBy('id');
        $isNewVehicle = $request->input('vehicle_id') === RentalService::NEW_VEHICLE;
        $newVehicleType = $isNewVehicle ? VehicleType::tryFrom((string) $request->input('vehicle_type')) : null;
        // A new vehicle's rules come from its class before it exists: Vehicle::forType(...)->requiresPlate().
        $newVehicle = $newVehicleType ? Vehicle::forType($newVehicleType) : null;
        $existingVehicle = $isNewVehicle ? null : $ownVehicles->get($request->input('vehicle_id'));
        $hasBattery = ($newVehicle ?? $existingVehicle)?->hasBattery() ?? false;
        $newWithPlate = $isNewVehicle && ($newVehicle?->requiresPlate() ?? true);
        $newWithBattery = $isNewVehicle && $hasBattery;
        $isDaily = $request->input('rental_type') === RentalType::Daily->value;
        $kwhRules = ['numeric', 'decimal:0,'.Vehicle::KWH_SCALE];

        return [
            'vehicle_id' => ['required', Rule::in([...$ownVehicles->keys()->map(fn ($id) => (string) $id), RentalService::NEW_VEHICLE])],
            'vehicle_type' => [Rule::excludeIf(! $isNewVehicle), 'required', Rule::enum(VehicleType::class)],
            'plate_number' => [
                Rule::excludeIf(! $newWithPlate), 'required', 'string', 'max:'.Vehicle::PLATE_MAX_LENGTH,
                'regex:'.Vehicle::PLATE_PATTERN, 'unique:vehicles,plate_number',
            ],
            'brand_model' => [Rule::excludeIf(! $isNewVehicle), 'required', 'string', 'max:'.Vehicle::BRAND_MODEL_MAX_LENGTH],
            'battery_capacity_kwh' => [Rule::excludeIf(! $newWithBattery), 'required', ...$kwhRules, 'gt:0', 'max:'.Vehicle::MAX_BATTERY_KWH],
            'current_battery_kwh' => [
                Rule::excludeIf(! $hasBattery), 'required', ...$kwhRules, 'min:0',
                $isNewVehicle ? 'lte:battery_capacity_kwh' : 'max:'.(float) $existingVehicle?->battery_capacity_kwh,
            ],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date_format:'.RentalService::DATE_FORMAT, 'after_or_equal:today'],
            'days' => [
                Rule::excludeIf(! $isDaily), 'required', 'integer', 'min:'.Rental::MIN_DAILY_DAYS,
                'max:'.Rental::maxDailyDays($this->requestedStartDate($request)),
            ],
        ];
    }

    /**
     * End date shown on step 1 when the form is refilled, or null while the days are not valid yet.
     */
    private function requestedEndDate(Request $request): ?CarbonImmutable
    {
        $start = $this->requestedStartDate($request);
        $type = RentalType::tryFrom((string) $request->input('rental_type')) ?? RentalType::Daily;
        $days = filter_var($request->input('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => Rental::MIN_DAILY_DAYS, 'max_range' => Rental::maxDailyDays($start)],
        ]);

        if ($type === RentalType::Daily && $days === false) {
            return null;
        }

        return Rental::calculateEndDate($type, $start, $days === false ? null : $days);
    }

    /**
     * Start date from the raw request, used to size the day limit; today when it is missing or invalid.
     */
    private function requestedStartDate(Request $request): CarbonImmutable
    {
        $value = $request->input('start_date');

        if (! is_string($value) || ! CarbonImmutable::canBeCreatedFromFormat($value, RentalService::DATE_FORMAT)) {
            return CarbonImmutable::today();
        }

        return CarbonImmutable::createFromFormat('!'.RentalService::DATE_FORMAT, $value);
    }
}
