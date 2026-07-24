{{-- <nav class="navbar navbar-dark bg-dark">

    <div class="container-fluid">

        <span class="navbar-brand">
            Mozio Admin Panel
        </span>

        <div class="text-white">
            {{ auth()->user()->name }}
        </div>

    </div>

</nav> --}}


{{--
    Navbar is now built directly into app.blade.php as the #topbar element.
    This file is kept for compatibility but outputs nothing.
    The topbar renders the page title via @yield('page-title') and the user avatar.
--}}
