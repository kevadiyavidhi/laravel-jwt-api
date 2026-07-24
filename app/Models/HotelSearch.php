<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\HotelBooking;

class HotelSearch extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'channel_id',
        'currency',
        'culture',
        'check_in',
        'check_out',
        'occupancies',
        'search_region',
        'nationality',
        'country_of_residence',
        'status',
    ];

    protected $casts = [
        'occupancies'   => 'array',
        'search_region' => 'array',
        'check_in'      => 'date',
        'check_out'     => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hotelBookings()
    {
        return $this->hasMany(HotelBooking::class, 'hotel_search_id');
    }
}