<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->nullable();           // returned by availability/init
            $table->string('channel_id');
            $table->string('currency', 10)->default('USD');
            $table->string('culture', 10)->default('en-US');
            $table->date('check_in');
            $table->date('check_out');
            $table->json('occupancies');                   // [{numOfAdults:2, childAges:[]}]
            $table->json('search_region')->nullable();     // circularRegion / polygonalRegion / hotelIds
            $table->string('nationality', 10)->nullable();
            $table->string('country_of_residence', 10)->nullable();
            $table->string('status')->default('pending'); // pending / in_progress / completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_searches');
    }
};