<?php

use App\Models\Garage;
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
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            // Restrict: rental history must survive, so its tenant, garage and vehicle cannot be deleted.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('garage_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->decimal('current_battery_kwh', Vehicle::KWH_PRECISION, Vehicle::KWH_SCALE)->nullable();
            $table->string('rental_type', self::ENUM_COLUMN_LENGTH);
            $table->unsignedSmallInteger('days')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('daily_price', Garage::PRICE_PRECISION, Garage::PRICE_SCALE);
            $table->decimal('kwh_price', Garage::PRICE_PRECISION, Garage::PRICE_SCALE)->nullable();
            $table->decimal('rental_cost', Garage::PRICE_PRECISION, Garage::PRICE_SCALE);
            $table->decimal('charging_kwh', Vehicle::KWH_PRECISION, Vehicle::KWH_SCALE)->default(0);
            $table->decimal('charging_cost', Garage::PRICE_PRECISION, Garage::PRICE_SCALE)->default(0);
            $table->decimal('total_cost', Garage::PRICE_PRECISION, Garage::PRICE_SCALE);
            $table->string('payment_proof');
            $table->timestamps();

            $table->index(['garage_id', 'start_date', 'end_date']);
            $table->index(['vehicle_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
