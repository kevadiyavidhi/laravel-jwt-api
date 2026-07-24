<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DatabaseController extends Controller
{
    public function index()
    {
        $users = User::all();
        // $bookings = Booking::join('users', )
        $query = DB::table('bookings')
            ->join('users', 'bookings.user_id', '=', 'users.id')
            ->select('bookings.*', 'users.email as user_email');
            
        // SELECT bookings.*, users.email
        // FROM bookings
        // JOIN users ON bookings.user_id = users.id;

        return response()->json($users);
    }
}
