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
        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            // An extension has no meaning without its rental.
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('extension_type', self::ENUM_COLUMN_LENGTH);
            $table->unsignedSmallInteger('months')->nullable();
            $table->unsignedSmallInteger('days');
            $table->date('old_end_date');
            $table->date('new_end_date');
            $table->decimal('additional_cost', Garage::PRICE_PRECISION, Garage::PRICE_SCALE);
            $table->string('payment_proof');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_extensions');
    }
};
