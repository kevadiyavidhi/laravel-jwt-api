{{-- <div class="col-md-2 bg-light vh-100 border-end">
    <div class="list-group list-group-flush mt-3">
        <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action">
            Dashboard
        </a>

        <a href="{{ route('admin.users') }}" class="list-group-item list-group-item-action">
            Users
        </a>

        <a href="{{ route('admin.bookings') }}" class="list-group-item list-group-item-action">
            Bookings
        </a>

        <a href="#" class="list-group-item list-group-item-action">
            Logs
        </a>

        <form action="{{ route('logout') }}" method="POST">
            @csrf

            <button class="list-group-item list-group-item-action text-danger">
                Logout
            </button>

        </form>

    </div>
</div> --}}


{{--
    Sidebar is now built directly into app.blade.php as the #sidebar element.
    This file is kept for compatibility but outputs nothing.
    Active state uses request()->routeIs() for automatic highlighting.
--}}