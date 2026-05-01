@extends('layouts.app')

@section('title', 'BobaAI Builder - Сравнение сборок')

@section('content')
<div class="compare-container">

    <div class="compare-header">
        <a href="{{ route('profile') }}" class="btn btn-outline btn-sm">
            <span class="icon icon-arrow-left"></span> Назад к профилю
        </a>
        <h2>Сравнение сборок</h2>
    </div>

    <div class="compare-table-wrap">
    <div class="compare-table">

        {{-- Заголовки --}}
        <div class="compare-row compare-titles">
            <div class="compare-label"></div>
            @foreach($builds as $build)
            <div class="compare-cell compare-build-title">
                <div class="compare-build-name">{{ $build->name }}</div>
                <div class="compare-build-date">{{ $build->created_at->format('d.m.Y') }}</div>
                <div class="compare-build-price">{{ number_format($build->total_price, 0, ',', ' ') }} ₽</div>
            </div>
            @endforeach
        </div>

        {{-- Назначение --}}
        <div class="compare-row">
            <div class="compare-label">Назначение</div>
            @foreach($builds as $build)
            <div class="compare-cell">
                {{ ['gaming' => 'Игровой', 'office' => 'Офисный', 'workstation' => 'Рабочая станция', 'streaming' => 'Стриминговый'][$build->purpose] ?? '—' }}
            </div>
            @endforeach
        </div>

        {{-- Компоненты --}}
        @foreach($components as $type => $data)
        <div class="compare-row {{ $data['differs'] ? 'compare-differs' : '' }}">
            <div class="compare-label">{{ $data['label'] }}</div>
            @foreach($data['items'] as $component)
            <div class="compare-cell">
                @if($component)
                    <div class="compare-component-name">{{ $component->name }}</div>
                    @if($type === 'ram' && ($component->quantity ?? 1) > 1)
                        <div class="compare-component-price">
                            {{ number_format($component->price * $component->quantity, 0, ',', ' ') }} ₽
                            <span class="text-gray-text">×{{ $component->quantity }}</span>
                        </div>
                    @else
                        <div class="compare-component-price">{{ number_format($component->price, 0, ',', ' ') }} ₽</div>
                    @endif
                @else
                    <span class="text-gray-text">—</span>
                @endif
            </div>
            @endforeach
        </div>
        @endforeach

        {{-- Итоговая цена --}}
        <div class="compare-row compare-total">
            <div class="compare-label">Итого</div>
            @foreach($builds as $build)
            <div class="compare-cell">
                {{ number_format($build->total_price, 0, ',', ' ') }} ₽
            </div>
            @endforeach
        </div>

    </div>
    </div>
</div>
@endsection