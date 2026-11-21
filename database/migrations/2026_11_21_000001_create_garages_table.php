<?php

use App\Models\Garage;
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
        Schema::create('garages', function (Blueprint $table) {
            $table->id();
            $table->char('code', Garage::CODE_LENGTH)->unique();
            $table->string('type', self::ENUM_COLUMN_LENGTH)->index();
            $table->decimal('daily_price', Garage::PRICE_PRECISION, Garage::PRICE_SCALE);
            $table->decimal('kwh_price', Garage::PRICE_PRECISION, Garage::PRICE_SCALE)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
