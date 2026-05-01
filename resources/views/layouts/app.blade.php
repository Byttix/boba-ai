<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'BobaAI Builder')</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('img/logo.ico') }}">

    <!-- Основные стили -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-icons.css') }}">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @stack('styles')
</head>
<body class="{{ request()->routeIs('chat.show') ? 'chat-show-page' : '' }}">
@include('partials.navbar')

<!-- Уведомления Laravel -->
@if(session('success'))
    <div class="alert alert-success" id="flash-message">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-error" id="flash-message">
        {{ session('error') }}
    </div>
@endif

<main class="main-content container">
    @yield('content')
</main>

<!-- Скрипты -->
<script src="{{ asset('js/script.js') }}"></script>

@stack('scripts')

@stack('body_end')
</body>
</html>
