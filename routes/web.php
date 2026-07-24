<?php

use App\Http\Controllers\Admin\BookingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\Hotel\HotelSearchController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/register', [AuthenticatedSessionController::class, 'store'])->name('register');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
Route::get('/users', [UserController::class, 'index'])->name('admin.users');
Route::get('/bookings', [BookingsController::class, 'index'])->name('admin.bookings.index');
Route::get('/bookings/{bookingId}/passengers', [BookingsController::class, 'passengers'])->name('admin.bookings.passengers');
Route::post('/admin/bookings/{id}/cancel', [BookingsController::class, 'cancel'])
    ->name('admin.bookings.cancel');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    });

// Route::post('/hotel/search', [HotelSearchController::class, 'init'])

require __DIR__.'/auth.php';
