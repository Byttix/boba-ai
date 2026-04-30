@extends('layouts.app')

@section('title', 'BobaAI Builder - Главная')

@section('content')
    <div class="dashboard">
        @auth
            <div class="chats-sidebar">
                <div class="chats-header">
                    <h3>Мои чаты</h3>
                    <form action="{{ route('chat.start') }}" method="POST">
                        @csrf
                        <input type="hidden" name="title" value="Новый чат {{ now()->format('d.m.Y') }}">
                        <input type="hidden" name="budget" value="50000">
                        <input type="hidden" name="purpose" value="gaming">
                        <button type="submit" class="btn btn-sm btn-outline">
                            <span class="icon icon-plus"></span> Новый
                        </button>
                    </form>
                </div>

                <div class="chats-list">
                    @forelse($chats as $userChat)
                        <div class="chat-item-container">
                            <a href="{{ route('chat.show', $userChat) }}" class="chat-item">
                                <div class="chat-item-header">
                                    <div class="chat-title">{{ $userChat->title }}</div>
                                    <div class="chat-date">{{ $userChat->updated_at->format('d.m.Y') }}</div>
                                </div>
                                <div class="chat-preview">{{ Str::limit($userChat->last_message, 30) }}</div>
                            </a>
                            <form action="{{ route('chat.destroy', $userChat) }}" method="POST" class="chat-delete-form"
                                  onsubmit="return confirm('Удалить этот чат?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-accent btn-sm" title="Удалить чат">
                                    <span class="icon icon-trash"></span>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="text-center text-gray-text py-4">
                            <span class="icon icon-comments" style="font-size: 2rem;"></span>
                            <p>У вас пока нет чатов</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endauth

        <div class="chat-main-area">
            <div class="chat-center">
                <h2 class="neon-text">BobaAI Builder</h2>
                <p>Приветствуем! Я - нейросеть для подбора комплектующих ПК. Задайте мне параметры вашего будущего компьютера, и я помогу собрать идеальную конфигурацию под ваш бюджет и задачи.</p>

                @auth
                    <div class="chat-start-container">
                        <form action="{{ route('chat.start') }}" method="POST" class="chat-start-form">
                            @csrf
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="chat_title">Название чата</label>
                                    <input type="text" id="chat_title" name="title" class="form-control"
                                           value="{{ old('title', 'Новый чат ' . now()->format('d.m.Y')) }}" required>
                                    @error('title') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                                <div class="form-group">
                                    <label for="chat_budget">Бюджет (₽)</label>
                                    <input type="number" id="chat_budget" name="budget" class="form-control"
                                           value="{{ old('budget', 50000) }}" required>
                                    @error('budget') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                                <div class="form-group">
                                    <label for="chat_purpose">Назначение</label>
                                    <select id="chat_purpose" name="purpose" class="form-control" required>
                                        <option value="gaming" {{ old('purpose', 'gaming') == 'gaming' ? 'selected' : '' }}>🎮 Игровой ПК</option>
                                        <option value="workstation" {{ old('purpose') == 'workstation' ? 'selected' : '' }}>💼 Рабочая станция</option>
                                        <option value="office" {{ old('purpose') == 'office' ? 'selected' : '' }}>🏢 Офисный ПК</option>
                                        <option value="streaming" {{ old('purpose') == 'streaming' ? 'selected' : '' }}>🎥 Стриминг</option>
                                    </select>
                                    @error('purpose') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                                <div class="form-group button-group">
                                    <button type="submit" class="btn btn-primary btn-start">
                                        <span class="icon icon-chat"></span> Начать чат
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary start-chat-btn pulse">
                        <span class="icon icon-register"></span> Войдите, чтобы начать
                    </a>
                @endauth

                <div class="ai-features">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <span class="icon icon-microchip"></span>
                        </div>
                        <h4>Умный подбор</h4>
                        <p>Нейросеть анализирует совместимость и производительность комплектующих</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <span class="icon icon-chart-line"></span>
                        </div>
                        <h4>Оптимизация бюджета</h4>
                        <p>Помогаем найти лучшие компоненты в рамках вашего бюджета</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <span class="icon icon-save"></span>
                        </div>
                        <h4>Сохранение сборки</h4>
                        <p>Храните все свои конфигурации в профиле</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
