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

        .booking-meta {
            font-size: 0.82rem;
            color: #6b7280;
            margin-bottom: 20px;
        }

        .booking-meta span {
            font-weight: 600;
            color: #374151;
        }

        .section-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
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

        .empty-state {
            padding: 32px;
            text-align: center;
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .back-btn {
            font-size: 0.82rem;
            color: #6b7280;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 16px;
        }

        .back-btn:hover {
            color: #1a1a2e;
        }
    </style>

    <div class="container box">

        {{-- Back --}}
        <a href="{{ route('admin.bookings') }}" class="back-btn">
            ← Back to Bookings
        </a>

        {{-- Title --}}
        <div class="page-title">Passenger Details</div>

        {{-- Booking Summary --}}
        <div class="booking-meta mt-2">
            Booking <span>#{{ $booking->id }}</span> &nbsp;·&nbsp;
            Confirmation <span>{{ $booking->confirmation_number ?? '—' }}</span> &nbsp;·&nbsp;
            Pickup <span>{{ \Carbon\Carbon::parse($booking->pickup_datetime)->format('d M Y, h:i A') }}</span> &nbsp;·&nbsp;
            Price <span>${{ number_format($booking->price ?? 0, 2) }}</span>
        </div>

        {{-- Passengers Table --}}
        <div class="section-label">Passengers ({{ $passengers->count() }})</div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Index</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Birth Date</th>
                        </t </thead>
                <tbody>
                    @forelse ($passengers as $index => $passenger)
                        <tr>
                            <td class="text-center fw-semibold">{{ $index + 1 }}</td>
                            <td>{{ $passenger->first_name }}</td>
                            <td>{{ $passenger->last_name }}</td>
                            <td>{{ $passenger->email ?? '—' }}</td>
                            <td>{{ $passenger->phone_number ?? '—' }}</td>
                            <td class="text-center">
                                {{ $passenger->birth_date ? \Carbon\Carbon::parse($passenger->birth_date)->format('d M Y') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No passengers found for this booking.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
@endsection
