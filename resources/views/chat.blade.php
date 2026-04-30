@extends('layouts.app')

@section('title', 'BobaAI Builder - ' . $chat->title)

@section('content')
    <div class="dashboard">
        <div class="chats-sidebar">
            <div class="chats-header">
                <h3>Мои чаты</h3>
                <form action="{{ route('chat.start') }}" method="POST" class="d-inline">
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
                @forelse(Auth::user()->chats()->latest()->take(10)->get() as $userChat)
                    <div class="chat-item-container">
                        <a href="{{ route('chat.show', $userChat) }}" class="chat-item {{ $chat->id == $userChat->id ? 'active' : '' }}">
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

        <div class="chat-main-area">
            @include('partials.chat-active', [
                'chat' => $chat,
                'messages' => $messages
            ])
        </div>
    </div>
@endsection
