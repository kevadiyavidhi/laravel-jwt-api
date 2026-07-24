<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $reservationId = $request->input('reservation_id');
        $confirmationNumber = $request->input('confirmation_number');
        $status = $request->input('status');
        $fromDate = $request->input('from_date');
        $bookingEngine = $request->input('booking_engine');

        $query = DB::table('users');

        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }

        $users = $query->orderBy('id', 'asc')
            ->get();

        $userIds = $users->pluck('id');

        $bookings = collect();

        if ($search) {
            $bookingsQuery = DB::table('bookings')
                ->join('users', 'bookings.user_id', '=', 'users.id')
                ->whereIn('bookings.user_id', $userIds)
                ->select('bookings.*', 'users.email as user_email');

            if ($reservationId) {
                $bookingsQuery->where('bookings.reservation_id', 'like', "%{$reservationId}%");
            }

            if ($confirmationNumber) {
                $bookingsQuery->where('bookings.confirmation_number', 'like', "%{$confirmationNumber}%");
            }

            if ($status) {
                $bookingsQuery->where('bookings.status', $status);
            }

            if ($fromDate) {
                $bookingsQuery->whereDate('bookings.pickup_datetime', '=', $fromDate);
            }

            if ($bookingEngine) {
                $query->where('bookings.booking_engine', $bookingEngine);
            }

            $bookings = $bookingsQuery
                ->orderBy('bookings.id', 'asc')
                ->get();
        }

        return view('admin.users', compact(
            'users',
            'bookings',
            'search',
            'reservationId',
            'confirmationNumber',
            'status',
            'fromDate'
        ));
    }
}
