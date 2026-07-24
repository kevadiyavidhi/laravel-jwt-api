<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MozioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingsController extends Controller
{
    public function index(Request $request)
    {
        $reservationId = $request->input('reservation_id');
        $confirmationNumber = $request->input('confirmation_number');
        $email = $request->input('email');
        $status = $request->input('status');
        $fromDate = $request->input('from_date');
        $bookingEngine = $request->input('booking_engine');

        $query = DB::table('bookings')
            ->join('users', 'bookings.user_id', '=', 'users.id')
            ->select('bookings.*', 'users.email as user_email');

        if ($reservationId) {
            $query->where('bookings.reservation_id', 'like', "%{$reservationId}%");
        }

        if ($confirmationNumber) {
            $query->where('bookings.confirmation_number', 'like', "%{$confirmationNumber}%");
        }

        if ($email) {
            $query->where('users.email', 'like', "%{$email}%");
        }

        if ($status) {
            $query->where('bookings.status', $status);
        }

        if ($fromDate) {
            $query->whereDate('bookings.pickup_datetime', '=', $fromDate);
        }

        if ($bookingEngine) {
            $query->where('bookings.booking_engine', $bookingEngine);
        }

        $bookings = $query
            ->orderBy('bookings.id', 'asc')
            ->paginate(5)
            ->appends($request->query());

        return view('admin.bookings', compact(
            'bookings',
            'reservationId',
            'confirmationNumber',
            'email',
            'status',
            'fromDate'
        ));
    }

    public function passengers(int $bookingId)
    {
        $booking = DB::table('bookings')
            ->where('id', $bookingId)
            ->first();

        if (! $booking) {
            abort(404, 'Booking not found.');
        }

        $passengers = DB::table('passengers')
            ->join('customers', 'passengers.customer_id', '=', 'customers.id')
            ->where('passengers.booking_id', $bookingId)
            ->select(
                'customers.id as customer_id',
                'customers.first_name',
                'customers.last_name',
                'customers.email',
                'customers.phone_number',
                'customers.birth_date'
            )
            ->get();

        return view('admin.passengers', compact('booking', 'passengers'));
    }

    public function cancel(int $bookingId, MozioService $mozioService)
    {
        $booking = DB::table('bookings')
            ->where('id', $bookingId)
            ->first();

        if (! $booking) {
            return redirect()->back()->with('error', 'Booking not found.');
        }

        if ($booking->status === 'cancelled') {
            return redirect()->back()->with('error', 'Booking is already cancelled.');
        }

        $bookingDetails = json_decode($booking->booking_details, true);

        $reservation = data_get($bookingDetails, 'data.reservations.0');

        $noticeHours = data_get(
            $reservation,
            'voyage.booking_details.cancellation_policy.0.notice',
            0
        );

        if ($noticeHours > 0 && $booking->pickup_datetime) {

            $pickupTime = Carbon::parse($booking->pickup_datetime);

            $lastCancellationTime = $pickupTime
                ->copy()
                ->subHours($noticeHours);

            if (now()->greaterThan($lastCancellationTime)) {

                return redirect()->back()->with(
                    'error',
                    "You can not cancel this booking because the cancellation notice time ({$noticeHours} hours before pickup) has passed."
                );
            }
        }

        $response = $mozioService->cancelReservation($booking->reservation_id);

        if (! $response['success']) {

            return redirect()->back()->with(
                'error',
                $response['message'] ?? 'Unable to cancel booking.'
            );
        }

        DB::table('bookings')
            ->where('id', $bookingId)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()->back()->with(
            'success',
            'Booking cancelled successfully.'
        );
    }
}
