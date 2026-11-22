<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            // An extension has no meaning without its rental.
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            // Whole months of the added days; the rest are charged per day.
            $table->unsignedSmallInteger('months');
            // All days added after old_end_date.
            $table->unsignedSmallInteger('days');
            $table->date('old_end_date');
            $table->date('new_end_date');
            $table->decimal('additional_cost', config('rules.garage.price_precision'), config('rules.garage.price_scale'));
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
