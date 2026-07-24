<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // User Statistics
        $totalUsers = User::count();

        $todayUsers = User::whereDate(
            'created_at',
            Carbon::today()
        )->count();

        // Booking Statistics
        $totalBookings = Booking::count();

        $todayBookings = Booking::whereDate(
            'created_at',
            Carbon::today()
        )->count();

        // Recent Users
        // $recentUsers = User::latest()
        //     ->take(5)
        //     ->get();

        // // Recent Bookings
        // $recentBookings = Booking::latest()
        //     ->take(5)
        //     ->get();

        $totalCancelledBookings = Booking::where('status', 'cancelled')->count();

        $activeBookings = Booking::where('status', 'confirmed')->count();

        return view('admin.dashboard', compact(
            'totalUsers',
            'todayUsers',
            'totalBookings',
            'todayBookings',
            // 'recentUsers',
            // 'recentBookings',
            'totalCancelledBookings',
            'activeBookings'
        ));
    }
}
