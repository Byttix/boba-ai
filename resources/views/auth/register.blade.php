@extends('layouts.app')

@section('title', 'BobaAI Builder - Регистрация')

@section('content')
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-header">
                <h2>Регистрация</h2>
                <p>Создайте новый аккаунт</p>
            </div>

            <form class="auth-form" method="POST" action="{{ route('register') }}">
                @csrf

                <div class="form-group">
                    <label for="registerName">Имя</label>
                    <input type="text" id="registerName" name="name"
                           placeholder="Ваше имя"
                           value="{{ old('name') }}"
                           >
                    @error('name')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="registerEmail">Email</label>
                    <input type="email" id="registerEmail" name="email"
                           placeholder="Ваш email"
                           value="{{ old('email') }}"
                           >
                    @error('email')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="registerPassword">Пароль</label>
                    <input type="password" id="registerPassword" name="password"
                           placeholder="Придумайте пароль"
                           >
                    <small class="text-muted">Пароль должен содержать не менее 8 символов, включая как минимум одну заглавную букву, одну строчную букву и одну цифру</small>
                    @error('password')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="registerPasswordConfirm">Подтверждение пароля</label>
                    <input type="password" id="registerPasswordConfirm"
                           name="password_confirmation"
                           placeholder="Повторите пароль"
                           >
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="icon icon-register"></span> Зарегистрироваться
                </button>
            </form>

            <div class="auth-footer">
                <p>Уже есть аккаунт? <a href="{{ route('login') }}">Войти</a></p>
            </div>
        </div>
    </div>
@endsection
