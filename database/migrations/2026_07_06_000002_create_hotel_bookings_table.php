<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_search_id')->constrained('hotel_searches')->cascadeOnDelete();
            $table->string('booking_id')->nullable();           // returned by book API
            $table->string('booking_ref_id')->nullable();       // our generated ref
            $table->string('hotel_id')->nullable();
            $table->string('token')->nullable();                // search token used
            $table->string('recommendation_id')->nullable();
            $table->json('rate_ids')->nullable();               // array of rate IDs booked
            $table->json('rooms_allocations')->nullable();      // room + rate + guests
            $table->json('billing_contact')->nullable();        // billing contact details
            $table->json('credit_card')->nullable();            // card if required
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('status')->default('pending');       // pending/confirmed/cancelled
            $table->json('booking_response')->nullable();       // raw API response
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};