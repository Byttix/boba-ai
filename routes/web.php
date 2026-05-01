<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    HomeController,
    AuthController,
    ProfileController,
};

// Главная
Route::get('/', [HomeController::class, 'index'])->name('home');

// Аутентификация
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// только для авторизованных
Route::middleware('auth')->group(function () {
    // Профиль
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update'); // Этот маршрут уже правильный

    // Сборки
    Route::get('/profile/builds', [ProfileController::class, 'builds'])->name('profile.builds');
    Route::post('/builds/create', [HomeController::class, 'saveBuild'])->name('build.save');
    Route::delete('/builds/{build}', [ProfileController::class, 'destroy'])->name('build.destroy');
    Route::get('/builds/{build}/pdf', [ProfileController::class, 'exportPdf'])->name('build.pdf');
    Route::get('/builds/compare', [ProfileController::class, 'compare'])->name('build.compare');
    Route::get('/api/builds/{build}/links', [HomeController::class, 'getBuildLinks'])->name('api.build.links');

    // Отзывы
    Route::post('/reviews/add', [ProfileController::class, 'addReview'])->name('review.add');

    // Чат
    Route::post('/chat/start', [HomeController::class, 'startChat'])->name('chat.start');
    Route::get('/chat/{chat}', [HomeController::class, 'chat'])->name('chat.show');
    Route::post('/chat/{chat}/send', [HomeController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat/{chat}/message', [HomeController::class, 'sendMessage'])->name('chat.message');
    Route::post('/chat/{chat}/save-build', [HomeController::class, 'saveBuildFromChat'])->name('chat.save-build');
    Route::delete('/chat/{chat}', [HomeController::class, 'deleteChat'])->name('chat.destroy');

    // Компоненты
    Route::get('/components/{type}', [HomeController::class, 'getComponents'])->name('components.list');
    Route::get('/component/{type}/{id}', [HomeController::class, 'showComponent'])->name('component.show');

    // API для компонентов
    Route::get('/api/components/{type}', [HomeController::class, 'apiGetComponents'])->name('api.components');
    Route::get('/ajax/components', [HomeController::class, 'ajaxGetComponents'])->name('ajax.components');

    // Проверка совместимости и рекомендации
    Route::post('/check-compatibility', [HomeController::class, 'checkCompatibility'])->name('compatibility.check');
    Route::post('/recommend-build', [HomeController::class, 'recommendBuild'])->name('build.recommend');
});
