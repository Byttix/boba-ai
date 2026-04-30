@php
    $chat = $chat ?? null;
    $messages = $messages ?? collect();

    // Найдем последнюю рекомендацию AI
    $lastRecommendation = null;
    if ($messages->isNotEmpty()) {
        $lastAiMessage = $messages->where('sender', 'ai')->last();
        if ($lastAiMessage && $lastAiMessage->metadata) {
            $metadata = json_decode($lastAiMessage->metadata, true);
            if (isset($metadata['type']) && $metadata['type'] === 'recommendation') {
                $lastRecommendation = $metadata;
            }
        }
    }
@endphp

@if($chat)
    <div class="chat-active-container">
        <div class="chat-header">
            <h3>{{ $chat->title }}</h3>
            <div class="chat-header-actions">
                <form action="{{ route('chat.destroy', $chat) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Вы уверены, что хотите удалить этот чат? Все сообщения будут удалены безвозвратно.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-accent btn-sm">
                        <span class="icon icon-trash"></span> Удалить чат
                    </button>
                </form>
                <a href="{{ route('home') }}" class="btn btn-sm btn-outline">
                    <span class="icon icon-arrow-left"></span> Назад
                </a>
            </div>
        </div>

        <div class="chat-messages-container" id="chatMessagesContainer">
            <div class="chat-messages" id="chatMessages">
                @forelse($messages as $message)
                    <div class="message {{ $message->sender }}">
                        <div class="message-sender">{{ $message->sender == 'user' ? 'Вы' : 'BobaAI' }}</div>
                        <div class="message-content">
                            <div class="message-text">{{ $message->message }}</div>
                            <div class="message-time" data-timestamp="{{ $message->created_at->getTimestamp() * 1000 }}">
                                {{ $message->created_at->format('H:i') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-text">
                        <span class="icon icon-comments" style="font-size: 3rem;"></span>
                        <p>Начните диалог с нейросетью</p>
                    </div>
                @endforelse
            </div>
        </div>

        @php
            $lastRecommendation = null;
            foreach ($messages->reverse() as $message) {
                if ($message->sender == 'ai' && $message->metadata) {
                    $metadata = json_decode($message->metadata, true);
                    if (isset($metadata['components']) && !empty($metadata['components'])) {
                        $lastRecommendation = $metadata;
                        break;
                    }
                }
            }
        @endphp

            <!-- Блок сохранения сборки (ВНЕ контейнера сообщений) -->
        @if($lastRecommendation && isset($lastRecommendation['components']))
            <div class="save-build-section visible" id="saveBuildSection">
                <h4>💾 Сохранить эту сборку</h4>
                <form action="{{ route('chat.save-build', $chat) }}" method="POST">
                    @csrf
                    <div class="save-build-form">
                        <input type="text" name="name"
                               value="Сборка из чата: {{ $chat->title }}"
                               class="save-build-input">
                        <button type="submit" class="btn btn-accent">
                            <span class="icon icon-save"></span> Сохранить
                        </button>
                    </div>
                    <p class="save-build-info">
                        Бюджет: {{ number_format($chat->budget, 0, ',', ' ') }} ₽ •
                        Назначение:
                        @if($chat->purpose === 'gaming')
                            Игровой
                        @elseif($chat->purpose === 'office')
                            Офисный
                        @elseif($chat->purpose === 'workstation')
                            Рабочая станция
                        @elseif($chat->purpose === 'streaming')
                            Для стримов
                        @endif
                    </p>
                </form>
            </div>
        @else
            <div class="save-build-section" id="saveBuildSection" style="display: none;"></div>
        @endif

        <div class="chat-input-container">
            <form action="{{ route('chat.message', $chat) }}" method="POST" class="chat-input-area">
                @csrf
                <textarea name="message" class="chat-input" id="messageInput" placeholder="Введите ваш вопрос или параметры сборки..." required></textarea>
                <button type="submit" class="btn btn-primary">
                    <span class="icon icon-paper-plane"></span> Отправить
                </button>
            </form>
        </div>
    </div>
@else
    <div class="chat-center">
        <h2 class="neon-text">Чат не найден</h2>
        <p>К сожалению, запрашиваемый чат не существует или у вас нет к нему доступа.</p>
        <a href="{{ route('home') }}" class="btn btn-primary start-chat-btn">
            <span class="icon icon-home">Вернуться на главную</span>
        </a>
    </div>
@endif
