<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LengthException;

/**
 * Garage use cases: listing and batch creation with sequential codes. Prices never change after creation.
 */
class GarageService
{
    private const GARAGES_PER_PAGE = 15;

    /**
     * Garages (optionally of one type) by type and code, each flagged with whether it is used today.
     *
     * @return LengthAwarePaginator<int, Garage>
     */
    public function paginate(?GarageType $type): LengthAwarePaginator
    {
        return Garage::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->withUsageOn(CarbonImmutable::today())
            ->orderBy('type')
            ->orderBy('code')
            ->paginate(self::GARAGES_PER_PAGE);
    }

    /**
     * For every garage type: the next free code and how many codes are left.
     *
     * @return Collection<string, array{next: string, remaining: int}> keyed by type value
     */
    public function nextCodes(): Collection
    {
        return collect(GarageType::cases())->mapWithKeys(fn (GarageType $type) => [$type->value => [
            'next' => Garage::formatCode($type, Garage::nextCodeNumber($type)),
            'remaining' => Garage::remainingCodeNumbers($type),
        ]]);
    }

    /**
     * Create a batch of garages of one type with the next free codes (C011, C012, ...).
     *
     * @param  int  $quantity  1 to config('rules.garage.max_batch_size'), and no more than the codes left
     * @param  float|null  $kwhPrice  charging price; stored only for garages that charge
     * @return Collection<int, string> the new codes, in order
     *
     * @throws InvalidArgumentException when the quantity is below config('rules.garage.min_batch_size')
     * @throws LengthException when the batch would go past code number 999
     */
    public function createBatch(GarageType $type, int $quantity, float $dailyPrice, ?float $kwhPrice): Collection
    {
        if ($quantity < config('rules.garage.min_batch_size')) {
            throw new InvalidArgumentException('A garage batch needs at least '.config('rules.garage.min_batch_size').' garage.');
        }

        return DB::transaction(function () use ($type, $quantity, $dailyPrice, $kwhPrice) {
            // Lock this type's rows so two admins creating at once never get the same numbers.
            $highestCode = Garage::query()->where('type', $type)->lockForUpdate()->max('code');
            $firstNumber = $highestCode === null ? 1 : Garage::codeNumber($highestCode) + 1;
            $lastNumber = $firstNumber + $quantity - 1;

            if ($lastNumber > config('rules.garage.max_code_number')) {
                throw new LengthException("Only {$type->prefix()} codes up to ".config('rules.garage.max_code_number').' exist.');
            }

            $now = now();
            $codes = collect(range($firstNumber, $lastNumber))->map(fn (int $number) => Garage::formatCode($type, $number));

            Garage::query()->insert($codes->map(fn (string $code) => [
                'code' => $code,
                'type' => $type->value,
                'daily_price' => $dailyPrice,
                'kwh_price' => $type->hasCharging() ? $kwhPrice : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return $codes;
        });
    }
}
