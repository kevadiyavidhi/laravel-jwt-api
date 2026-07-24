<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'search_id',
        'result_id',
        'partner_tracking_id',
        'reservation_id',
        'status',
        'customer_ids',
    ];

    protected $casts = [
        'customer_ids' => 'array',
    ];
}
