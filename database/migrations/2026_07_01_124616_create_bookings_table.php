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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('search_id');
            $table->string('reservation_id')->unique();
            $table->string('confirmation_number')->nullable();
            $table->string('pickup_address');
            $table->string('dropoff_address');
            $table->dateTime('pickup_datetime')->nullable();
            $table->dateTime('return_pickup_datetime')->nullable();
            $table->json('selected_amenities')->nullable();
            $table->string('currency')->default('USD');
            $table->decimal('price', 10, 2)->nullable();

            $table->json('booking_details')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
