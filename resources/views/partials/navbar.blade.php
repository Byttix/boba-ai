<nav class="navbar">
    <div class="container nav-container">
        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('img/logo.svg') }}" alt="BobaAI Builder" class="logo-icon" style="height: 32px; width: auto;">
            <span>BobaAI Builder</span>
        </a>

        <div class="nav-links">
            <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                Главная
            </a>
            <a href="{{ route('profile') }}" class="nav-link {{ request()->routeIs('profile*') ? 'active' : '' }}">
                Профиль
            </a>
        </div>

        <div class="auth-buttons">
            @auth
                @if(request()->routeIs('chat.show'))
                    <a href="{{ route('profile') }}" class="profile-name-mobile">
                        {{ Auth::user()->name }}
                    </a>
                @else
                    <a href="{{ route('profile') }}" class="profile-name-link">
                        {{ Auth::user()->name }}
                    </a>
                @endif
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">
                        <span class="icon-logout"></span> Выйти
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-outline btn-sm">
                    <span class="icon-login"></span> Вход
                </a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">
                    <span class="icon-register"></span> Регистрация
                </a>
            @endauth
        </div>
    </div>
</nav>
