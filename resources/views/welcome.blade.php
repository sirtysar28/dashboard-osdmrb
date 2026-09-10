@guest
    @if (Route::has('login'))
        <a href="{{ route('login') }}">Log in</a>
    @endif

    @if (Route::has('register'))
        <a href="{{ route('register') }}">Register</a>
    @endif
@else
    <a href="{{ url('/dashboard') }}">Dashboard</a>
@endguest
