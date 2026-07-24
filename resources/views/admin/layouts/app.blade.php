<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Mozio Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            margin: 0;
        }

        /* ── Sidebar ── */
        #sidebar {
            width: 240px;
            min-height: 100vh;
            background: #0f172a;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 20px 24px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        }

        .sidebar-brand .brand-icon {
            width: 32px;
            height: 32px;
            background: #6366f1;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }

        .sidebar-brand .brand-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.01em;
            display: block;
        }

        .sidebar-brand .brand-sub {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
        }

        .nav-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0 12px;
            margin: 16px 0 6px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s;
            margin-bottom: 2px;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #e2e8f0;
        }

        .sidebar-link.active {
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            border-left-color: #6366f1;
        }

        .sidebar-link i {
            font-size: 1rem;
            width: 18px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.07);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            color: #f87171;
            background: none;
            border: none;
            width: 100%;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
            text-align: left;
        }

        .logout-btn:hover {
            background: rgba(248, 113, 113, 0.1);
        }

        /* ── Topbar ── */
        #topbar {
            position: fixed;
            top: 0;
            left: 240px;
            right: 0;
            height: 60px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 99;
        }

        .topbar-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            background: #6366f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
        }

        .user-role {
            font-size: 0.72rem;
            color: #94a3b8;
        }

        /* ── Main ── */
        #main {
            margin-left: 240px;
            margin-top: 60px;
            padding: 28px;
            min-height: calc(100vh - 60px);
        }

        /* ── Footer ── */
        #footer {
            margin-left: 240px;
            background: #fff;
            border-top: 1px solid #e2e8f0;
            padding: 14px 28px;
            font-size: 0.78rem;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>

<body>

    {{-- Sidebar --}}
    <aside id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="bi bi-grid-fill text-white" style="font-size:0.9rem;"></i>
            </div>
            <span class="brand-name">Mozio Admin</span>
            <span class="brand-sub">Control Panel</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Main</div>

            <a href="{{ route('admin.dashboard') }}"
                class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a><br>

            <a href="{{ route('admin.users') }}"
                class="sidebar-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                <i class="bi bi-people"></i> Users
            </a><br>

            <a href="{{ route('admin.bookings.index') }}"
                class="sidebar-link {{ request()->routeIs('admin.bookings*') ? 'active' : '' }}">
                <i class="bi bi-calendar-check"></i> Bookings
            </a><br>

            {{-- <div class="nav-label">System</div>

            <a href="#" class="sidebar-link">
                <i class="bi bi-file-text"></i> Logs
            </a> --}}
        </nav>

        <div class="sidebar-footer">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="logout-btn">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- Topbar --}}
    <header id="topbar">
        <div class="topbar-title">
            @yield('page-title', 'Dashboard')
        </div>
        <div class="topbar-user">
            <div class="user-avatar">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-role">{{ ucfirst(auth()->user()->role ?? 'user') }}</div>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main id="main">
        @yield('content')
    </main>

    {{-- Footer --}}
    @include('admin.layouts.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
