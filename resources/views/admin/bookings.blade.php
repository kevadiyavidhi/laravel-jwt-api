@extends('admin.layouts.app')

@section('content')
    <style>
        .box {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-top: 24px;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 16px;
        }

        .table th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            padding: 12px 16px;
        }

        .table td {
            font-size: 0.85rem;
            padding: 12px 16px;
            vertical-align: middle;
            color: #374151;
        }

        .reservation-id {
            font-family: monospace;
            font-size: 0.78rem;
            color: #6b7280;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .address-cell {
            font-size: 0.82rem;
            color: #374151;
            max-width: 160px;
        }

        .badge-confirmation {
            background-color: #eff6ff;
            color: #1d4ed8;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .price-cell {
            font-weight: 700;
            color: #059669;
            white-space: nowrap;
        }

        .date-cell {
            font-size: 0.8rem;
            white-space: nowrap;
            color: #4b5563;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
        }

        .total-count {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .empty-state {
            padding: 32px;
            text-align: center;
            color: #9ca3af;
            font-size: 0.9rem;
        }
    </style>

    <div class="container box">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="page-title mb-1">
                    All Bookings
                </h3>

                <div class="sub-title">
                    Total Bookings :
                    <strong>{{ $bookings->total() }}</strong>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.bookings.index') }}" class="row g-2 mb-4" id="bookingFilterForm">

            <div class="col-md-3">
                <input type="text" name="reservation_id" id="filterReservationId" class="form-control"
                    placeholder="Reservation ID" value="{{ $reservationId ?? '' }}">
            </div>

            <div class="col-md-2">
                <input type="text" name="confirmation_number" id="filterConfirmationNumber" class="form-control"
                    placeholder="Confirmation #" value="{{ $confirmationNumber ?? '' }}">
            </div>

            <div class="col-md-2">
                <input type="text" name="email" id="filterEmail" class="form-control" placeholder="User Email"
                    value="{{ $email ?? '' }}">
            </div>

            <div class="col-md-2">
                <select name="status" id="filterStatus" class="form-select">
                    <option value="">All Status</option>
                    <option value="confirmed" {{ ($status ?? '') === 'confirmed' ? 'selected' : '' }}>Confirmed
                    </option>
                    <option value="cancelled" {{ ($status ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled
                    </option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="booking_engine" class="form-select">
                    <option value="">All Engines</option>
                    <option value="mozio" {{ ($bookingEngine ?? '') === 'mozio' ? 'selected' : '' }}>Mozio</option>
                    <option value="hotel_nexus" {{ ($bookingEngine ?? '') === 'hotel_nexus' ? 'selected' : '' }}>Hotel
                        Nexus</option>
                </select>
            </div>

            <div class="col-md-2">
                <input type="date" name="from_date" id="fromDate" class="form-control" value="{{ $fromDate ?? '' }}">
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-dark w-100">Filter</button>
            </div>

            @if (
                ($reservationId ?? null) ||
                    ($confirmationNumber ?? null) ||
                    ($email ?? null) ||
                    ($status ?? null) ||
                    ($fromDate ?? null))
                <div class="col-auto">
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary"
                        id="clearBookingFilters">
                        Clear Filters
                    </a>
                </div>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Index</th>
                        <th>User Email</th>
                        <th>Reservation ID</th>
                        <th>Confirmation</th>
                        <th>Pickup Address</th>
                        <th>Dropoff Address</th>
                        <th>Pickup Date</th>
                        <th>Return Date</th>
                        <th>Price</th>
                        <th>Passengers</th>
                        <th>Status</th>
                        <th>Booking Engine</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td class="text-center fw-semibold">{{ $booking->id }}</td>
                            <td>{{ $booking->user_email }}</td>
                            <td>
                                <span class="reservation-id" title="{{ $booking->reservation_id }}">
                                    {{ $booking->reservation_id }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($booking->confirmation_number)
                                    <span class="badge-confirmation">{{ $booking->confirmation_number }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="address-cell">{{ $booking->pickup_address }}</td>
                            <td class="address-cell">{{ $booking->dropoff_address }}</td>
                            <td class="text-center date-cell">
                                {{ $booking->pickup_datetime ? \Carbon\Carbon::parse($booking->pickup_datetime)->format('d M Y, h:i A') : '—' }}
                            </td>
                            <td class="text-center date-cell">
                                {{ $booking->return_pickup_datetime
                                    ? \Carbon\Carbon::parse($booking->return_pickup_datetime)->format('d M Y, h:i A')
                                    : '—' }}
                            </td>
                            <td class="text-center price-cell">
                                ${{ number_format($booking->price ?? 0, 2) }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.bookings.passengers', $booking->id) }}"
                                    class="btn btn-sm px-3 py-1"
                                    style="background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;
               font-size: 0.75rem; font-weight: 600; border-radius: 20px;
               letter-spacing: 0.03em; white-space: nowrap;">
                                    View Passengers
                                </a>
                            </td>

                            <td class="text-center">
                                @if ($booking->booking_engine === 'hotel_nexus')
                                    <span class="badge bg-info">Hotel Nexus</span>
                                @else
                                    <span class="badge bg-primary">Mozio</span>
                                @endif
                            </td>

                            @php
                                $bookingDetails = json_decode($booking->booking_details, true);

                                $noticeHours = data_get(
                                    $bookingDetails,
                                    'data.reservations.0.voyage.booking_details.cancellation_policy.0.notice',
                                    0,
                                );

                                $canCancel = true;

                                if ($noticeHours > 0 && $booking->pickup_datetime) {
                                    $lastCancellationTime = \Carbon\Carbon::parse($booking->pickup_datetime)->subHours(
                                        $noticeHours,
                                    );
                                    $canCancel = now()->lessThanOrEqualTo($lastCancellationTime);
                                }
                            @endphp

                            <td class="text-center">
                                @if ($booking->status == 'cancelled')
                                    <span class="badge bg-danger">Cancelled</span>
                                @else
                                    <span class="badge bg-success">Confirmed</span>
                                @endif
                            </td>

                            <td class="text-center">
                                @if ($booking->status == 'cancelled')
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        Cancelled
                                    </button>
                                @elseif (!$canCancel)
                                    <button class="btn btn-warning btn-sm" disabled
                                        title="Cancellation notice period ({{ $noticeHours }} hours before pickup) has expired">
                                        Cancellation Expired
                                    </button>
                                @else
                                    <form action="{{ route('admin.bookings.cancel', $booking->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to cancel this booking?')">
                                            Cancel Booking
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="empty-state">No bookings match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $bookings->links() }}
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script>
        $(function() {

            $('#filterStatus, #fromDate').on('change', function() {
                $('#bookingFilterForm').trigger('submit');
            });

            $('#filterReservationId, #filterConfirmationNumber, #filterEmail').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#bookingFilterForm').trigger('submit');
                }
            });
        });
    </script>
@endsection
