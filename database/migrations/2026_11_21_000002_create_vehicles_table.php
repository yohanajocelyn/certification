<?php

use App\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENUM_COLUMN_LENGTH = 20;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            // A vehicle has no meaning without its owner.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', self::ENUM_COLUMN_LENGTH);
            // Nullable only for bicycles; MySQL allows many NULLs in a unique column.
            $table->string('plate_number', config('rules.vehicle.plate_max_length'))->nullable()->unique();
            $table->string('brand_model', config('rules.vehicle.brand_model_max_length'));
            $table->decimal('battery_capacity_kwh', config('rules.vehicle.kwh_precision'), config('rules.vehicle.kwh_scale'))->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
