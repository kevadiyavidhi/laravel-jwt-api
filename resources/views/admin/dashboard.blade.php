@extends('admin.layouts.app')

@section('page-title', 'Dashboard')

@section('content')
    <style>
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: box-shadow 0.15s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .stat-icon.indigo {
            background: #eef2ff;
            color: #6366f1;
        }

        .stat-icon.emerald {
            background: #f0fdf4;
            color: #10b981;
        }

        .stat-icon.amber {
            background: #fffbeb;
            color: #f59e0b;
        }

        .stat-icon.rose {
            background: #fff1f2;
            color: #f43f5e;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .panel {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .panel-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }

        .panel-count {
            font-size: 0.72rem;
            background: #f1f5f9;
            color: #64748b;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .panel .table {
            margin: 0;
        }

        .panel .table th {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            background: #f8fafc;
            padding: 10px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .panel .table td {
            font-size: 0.85rem;
            padding: 12px 20px;
            color: #374151;
            border-bottom: 1px solid #f8fafc;
            vertical-align: middle;
        }

        .panel .table tbody tr:last-child td {
            border-bottom: none;
        }

        .panel .table tbody tr:hover td {
            background: #f8fafc;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px;
        }

        .page-header p {
            font-size: 0.82rem;
            color: #94a3b8;
            margin: 0;
        }

        .empty-row td {
            text-align: center;
            color: #94a3b8;
            padding: 28px !important;
            font-size: 0.85rem;
        }
    </style>

    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, {{ auth()->user()->name }}. </p>
    </div>

    {{-- Stat Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon indigo"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value">{{ $totalUsers }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon emerald"><i class="bi bi-person-plus-fill"></i></div>
                <div>
                    <div class="stat-label">Today's Users</div>
                    <div class="stat-value">{{ $todayUsers }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon amber"><i class="bi bi-calendar-check-fill"></i></div>
                <div>
                    <div class="stat-label">Total Bookings</div>
                    <div class="stat-value">{{ $totalBookings }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon amber"><i class="bi bi-calendar-date-fill"></i></div>
                <div>
                    <div class="stat-label">Today's Bookings</div>
                    <div class="stat-value">{{ $todayBookings }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon emerald">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Active Bookings</div>
                    <div class="stat-value">{{ $activeBookings }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon rose"> <i class="bi bi-x-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Total Cancelled Bookings</div>
                    <div class="stat-value">{{ $totalCancelledBookings }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
