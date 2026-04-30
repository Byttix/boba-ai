<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; }

        .header { background: #1a1a2e; color: #fff; padding: 24px 32px; }
        .header h1 { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
        .header p { font-size: 12px; color: #a0a0c0; margin-top: 4px; }

        .build-title { padding: 20px 32px 0; }
        .build-title h2 { font-size: 18px; font-weight: 700; }
        .build-meta { font-size: 11px; color: #666; margin-top: 4px; }

        .summary-table { width: calc(100% - 64px); margin: 16px 32px; border-collapse: collapse; border: 1px solid #e0e0f0; border-radius: 8px; }
        .summary-table td { padding: 14px 20px; text-align: center; border-right: 1px solid #e0e0f0; }
        .summary-table td:last-child { border-right: none; }
        .summary-label { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-value { font-size: 16px; font-weight: 700; color: #1a1a2e; margin-top: 2px; }

        .section { margin: 20px 32px 0; }
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px; border-bottom: 1px solid #e0e0f0; padding-bottom: 6px; }

        .components-table { width: 100%; border-collapse: collapse; }
        .components-table tr:nth-child(even) td { background: #f8f8fc; }
        .components-table td { padding: 9px 12px; font-size: 12px; }
        .components-table td.name { color: #1a1a2e; width: auto; }
        .components-table td.price { width: 120px; text-align: right; font-weight: 700; color: #4a4a8a; white-space: nowrap; }
        .total-row td { padding: 11px 12px; background: #1a1a2e; color: #fff; font-weight: 700; font-size: 13px; }
        .total-row td.price { color: #a0c4ff; text-align: right; }

        .footer { margin: 28px 32px 0; padding-top: 14px; border-top: 1px solid #e0e0f0; font-size: 10px; color: #aaa; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <h1>BobaAI Builder</h1>
    <p>Подбор комплектующих для ПК</p>
</div>

<div class="build-title">
    <h2>{{ $build->name }}</h2>
    <div class="build-meta">
        Создана: {{ $build->created_at->format('d.m.Y') }}
        @if($build->purpose)
            &nbsp;·&nbsp; Назначение: {{ ['gaming' => 'Игровой', 'office' => 'Офисный', 'workstation' => 'Рабочая станция', 'streaming' => 'Стриминговый'][$build->purpose] ?? $build->purpose }}
        @endif
    </div>
</div>

<table class="summary-table">
    <tr>
        <td>
            <div class="summary-label">Итоговая стоимость</div>
            <div class="summary-value">{{ number_format($build->total_price, 0, ',', ' ') }} ₽</div>
        </td>
        @if($build->budget)
        <td>
            <div class="summary-label">Бюджет</div>
            <div class="summary-value">{{ number_format($build->budget, 0, ',', ' ') }} ₽</div>
        </td>
        @endif
    </tr>
</table>

<div class="section">
    <div class="section-title">Комплектующие</div>
    <table class="components-table">
        @foreach($components as $type => $component)
        <tr>
            <td class="name">
                {{ $component->name }}
                @if($type === 'Оперативная память' && isset($component->quantity) && $component->quantity > 1)
                    <span style="color:#888"> ×{{ $component->quantity }}</span>
                @endif
            </td>
            <td class="price">
                @if($type === 'Оперативная память' && isset($component->quantity) && $component->quantity > 1)
                    {{ number_format($component->price * $component->quantity, 0, ',', ' ') }} ₽
                @else
                    {{ number_format($component->price, 0, ',', ' ') }} ₽
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td>Итого</td>
            <td class="price">{{ number_format($build->total_price, 0, ',', ' ') }} ₽</td>
        </tr>
    </table>
</div>

<div class="footer">
    Сформировано {{ now()->format('d.m.Y') }} · BobaAI Builder
</div>

</body>
</html>