<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin pages to list garages and create them in batches per type.
 */
class GarageController extends Controller
{
    private const GARAGES_PER_PAGE = 15;

    /**
     * List garages, optionally of one type, with whether each one is used today.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(GarageType::class)],
        ]);
        $selectedType = GarageType::tryFrom($filters['type'] ?? '');

        $garages = Garage::query()
            ->when($selectedType, fn (Builder $query) => $query->where('type', $selectedType))
            ->withUsageOn(CarbonImmutable::today())
            ->orderBy('type')
            ->orderBy('code')
            ->paginate(self::GARAGES_PER_PAGE)
            ->withQueryString();

        return view('admin.garages.index', [
            'garages' => $garages,
            'types' => GarageType::cases(),
            'selectedType' => $selectedType,
        ]);
    }

    /**
     * Show the batch form, with the next free code and room left for each type.
     */
    public function create(): View
    {
        $codeInfo = [];

        foreach (GarageType::cases() as $type) {
            $codeInfo[$type->value] = [
                'next' => Garage::formatCode($type, Garage::nextCodeNumber($type)),
                'remaining' => Garage::remainingCodeNumbers($type),
            ];
        }

        return view('admin.garages.create', [
            'types' => GarageType::cases(),
            'codeInfo' => $codeInfo,
        ]);
    }

    /**
     * Validate and create a batch of garages with sequential codes.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules($request));
        $type = GarageType::from($validated['type']);

        $codes = Garage::createBatch(
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
            ? min(Garage::MAX_BATCH_SIZE, Garage::remainingCodeNumbers($type))
            : Garage::MAX_BATCH_SIZE;
        $priceRules = ['numeric', 'decimal:0,'.Garage::PRICE_SCALE, 'gt:0', 'max:'.Garage::MAX_PRICE];

        return [
            'type' => ['required', Rule::enum(GarageType::class)],
            'quantity' => ['required', 'integer', 'min:'.Garage::MIN_BATCH_SIZE, 'max:'.$maxQuantity],
            'daily_price' => ['required', ...$priceRules],
            'kwh_price' => [Rule::requiredIf($hasCharging), Rule::prohibitedIf(! $hasCharging), 'nullable', ...$priceRules],
        ];
    }

    /**
     * Success text naming the new codes, e.g. "5 Garasi Mobil baru dibuat: C011 – C015."
     *
     * @param  list<string>  $codes
     */
    private function batchCreatedMessage(GarageType $type, array $codes): string
    {
        $codeRange = count($codes) === 1 ? $codes[0] : $codes[0].' – '.end($codes);

        return count($codes).' '.$type->label().' baru dibuat: '.$codeRange.'.';
    }
}
