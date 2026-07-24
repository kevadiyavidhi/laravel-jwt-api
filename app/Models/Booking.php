<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $casts = [
        'booking_details' => 'array',
        'selected_amenities' => 'array',
        'cancelled_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'booking_engine',
        'search_id',
        'reservation_id',
        'confirmation_number',
        'pickup_address',
        'dropoff_address',
        'pickup_datetime',
        'return_pickup_datetime',
        'selected_amenities',
        'currency',
        'price',
        'status',
        'cancelled_at',
        'booking_details',
    ];
}
