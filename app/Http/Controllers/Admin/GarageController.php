<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin pages to list garages and create them in batches per type.
 */
class GarageController extends Controller
{
    public function __construct(private readonly GarageService $garages) {}

    /**
     * List garages, optionally of one type, with whether each one is used today.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(GarageType::class)],
        ]);
        $selectedType = GarageType::tryFrom($filters['type'] ?? '');

        return view('admin.garages.index', [
            'garages' => $this->garages->paginate($selectedType)->withQueryString(),
            'types' => GarageType::cases(),
            'selectedType' => $selectedType,
        ]);
    }

    /**
     * Show the batch form, with the next free code and room left for each type.
     */
    public function create(): View
    {
        return view('admin.garages.create', [
            'types' => GarageType::cases(),
            'codeInfo' => $this->garages->nextCodes(),
        ]);
    }

    /**
     * Validate and create a batch of garages with sequential codes.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules($request));
        $type = GarageType::from($validated['type']);

        $codes = $this->garages->createBatch(
            $type,
            (int) $validated['quantity'],
            (float) $validated['daily_price'],
            isset($validated['kwh_price']) ? (float) $validated['kwh_price'] : null,
        );

        return redirect()
            ->route('admin.garages.index', ['type' => $type->value])
            ->with('success', $this->batchCreatedMessage($type, $codes));
    }

    /**
     * Rules for a garage batch. The quantity limit depends on the codes left for the chosen type,
     * and the kWh price belongs only to garages that charge.
     *
     * @return array<string, mixed>
     */
    private function validationRules(Request $request): array
    {
        $type = GarageType::tryFrom((string) $request->input('type'));
        $hasCharging = $type?->hasCharging() ?? false;
        $maxQuantity = $type
            ? min(config('rules.garage.max_batch_size'), Garage::remainingCodeNumbers($type))
            : config('rules.garage.max_batch_size');
        $priceRules = ['numeric', 'decimal:0,'.config('rules.garage.price_scale'), 'gt:0', 'max:'.config('rules.garage.max_price')];

        return [
            'type' => ['required', Rule::enum(GarageType::class)],
            'quantity' => ['required', 'integer', 'min:'.config('rules.garage.min_batch_size'), 'max:'.$maxQuantity],
            'daily_price' => ['required', ...$priceRules],
            'kwh_price' => [Rule::requiredIf($hasCharging), Rule::prohibitedIf(! $hasCharging), 'nullable', ...$priceRules],
        ];
    }

    /**
     * Success text naming the new codes, e.g. "5 Garasi Mobil baru dibuat: C011 – C015."
     *
     * @param  Collection<int, string>  $codes
     */
    private function batchCreatedMessage(GarageType $type, Collection $codes): string
    {
        $codeRange = $codes->count() === 1 ? $codes->first() : $codes->first().' – '.$codes->last();

        return $codes->count().' '.$type->label().' baru dibuat: '.$codeRange.'.';
    }
}
