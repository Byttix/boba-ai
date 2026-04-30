<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Поле Email обязательно для заполнения.',
            'email.email' => 'Введите корректный Email адрес.',
            'password.required' => 'Поле Пароль обязательно для заполнения.',
        ]);

        $rateLimitKey = 'login:' . Str::lower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return back()->withErrors([
                'email' => "Слишком много попыток входа. Повторите через {$seconds} сек.",
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->remember)) {
            RateLimiter::clear($rateLimitKey);
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        RateLimiter::hit($rateLimitKey, 60);

        return back()->withErrors([
            'email' => 'Неверный email или пароль.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'name.max' => 'Поле Имя максимально вмещает 50 символов.',
            'name.required' => 'Поле Имя обязательно для заполнения.',
            'email.required' => 'Поле Email обязательно для заполнения.',
            'email.email' => 'Введите корректный Email адрес.',
            'email.unique' => 'Этот Email уже зарегистрирован.',
            'password.required' => 'Поле Пароль обязательно для заполнения.',
            'password.min' => 'Пароль должен содержать минимум 8 символов.',
            'password.mixed' => 'Пароль должен содержать заглавные и строчные буквы.',
            'password.numbers' => 'Пароль должен содержать хотя бы одну цифру.',
            'password.confirmed' => 'Пароли не совпадают.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect('/')->with('success', 'Регистрация прошла успешно! Добро пожаловать!');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Вы успешно вышли из системы.');
    }
}
