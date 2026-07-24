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
            margin-bottom: 4px;
        }

        .section-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
            margin-top: 24px;
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

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .reservation-id {
            font-family: monospace;
            font-size: 0.78rem;
            color: #6b7280;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
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

        .badge-role-admin {
            background-color: #fef3c7;
            color: #d97706;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
        }

        .badge-role-user {
            background-color: #f0fdf4;
            color: #16a34a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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

        .search-result-meta {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
        }

        .empty-state {
            padding: 32px;
            text-align: center;
            color: #9ca3af;
            font-size: 0.9rem;
        }
    </style>

    <div class="container box">

        <div class="page-title">User Search</div>

        <form method="GET" action="{{ url('/users') }}" class="d-flex gap-2 mb-4 mt-3">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search by name or email..."
                class="form-control" />
            <button type="submit" class="btn btn-dark px-4">Search</button>
            @if ($search)
                <a href="{{ url('/users') }}" class="btn btn-outline-secondary px-3">Clear</a>
            @endif
        </form>

        @if ($search)
            <div class="section-label">
                Bookings for "{{ $search }}"
                @if ($users->isNotEmpty())
                    ({{ $users->pluck('name')->implode(', ') }})
                @endif
            </div>

            {{-- Server-side filters: GET form, works across the full result set --}}
            <form method="GET" action="{{ url('/users') }}" class="row g-2 mb-4" id="bookingFilterForm">
                {{-- keep the user search term when filters are submitted --}}
                <input type="hidden" name="search" value="{{ $search }}">

                <div class="col-md-3">
                    <input type="text" name="reservation_id" id="filterReservationId" class="form-control"
                        placeholder="Reservation ID" value="{{ $reservationId ?? '' }}">
                </div>

                <div class="col-md-2">
                    <input type="text" name="confirmation_number" id="filterConfirmationNumber" class="form-control"
                        placeholder="Confirmation #" value="{{ $confirmationNumber ?? '' }}">
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
                    <input type="date" name="from_date" id="fromDate" class="form-control"
                        value="{{ $fromDate ?? '' }}">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-dark w-100">Filter</button>
                </div>

                @if (($reservationId ?? null) || ($confirmationNumber ?? null) || ($status ?? null) || ($fromDate ?? null))
                    <div class="col-auto">
                        <a href="{{ url('/users') }}?search={{ urlencode($search) }}" class="btn btn-outline-secondary"
                            id="clearBookingFilters">
                            Clear Filters
                        </a>
                    </div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="bookingsTable">
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
                                        $lastCancellationTime = \Carbon\Carbon::parse(
                                            $booking->pickup_datetime,
                                        )->subHours($noticeHours);

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
        @else
            <div class="section-label">All Users</div>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>Index</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="text-center fw-semibold">{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td class="text-center">
                                    @if (($user->role ?? 'user') === 'admin')
                                        <span class="badge-role-admin">Admin</span>
                                    @else
                                        <span class="badge-role-user">User</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script>
        $(function() {
            // Auto-submit filter form on dropdown/date change
            $('#filterStatus, #fromDate').on('change', function() {
                $('#bookingFilterForm').trigger('submit');
            });

            // Submit text filters on Enter key
            $('#filterReservationId, #filterConfirmationNumber').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#bookingFilterForm').trigger('submit');
                }
            });
        });
    </script>
@endsection
