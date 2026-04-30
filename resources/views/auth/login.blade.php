@extends('layouts.app')

@section('title', 'BobaAI Builder - Вход')

@section('content')
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-header">
                <h2>Вход в систему</h2>
                <p>Используйте свои данные для входа</p>
            </div>

            <form class="auth-form" method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label for="loginEmail">Email</label>
                    <input type="email" id="loginEmail" name="email"
                           placeholder="Ваш email"
                           value="{{ old('email') }}"
                           >
                    @error('email')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="loginPassword">Пароль</label>
                    <input type="password" id="loginPassword" name="password"
                           placeholder="Ваш пароль"
                           >
                    @error('password')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="icon icon-login"></span> Войти
                </button>
            </form>

            <div class="auth-footer">
                <p>Нет аккаунта? <a href="{{ route('register') }}">Зарегистрироваться</a></p>
            </div>
        </div>
    </div>
@endsection
