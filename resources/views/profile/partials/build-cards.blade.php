@forelse($builds as $build)
    <div class="build-card">
        <div class="build-header">
            <div>
                <div class="build-title">{{ $build->name }}</div>
                <div class="build-date">{{ $build->created_at->format('d.m.Y') }}</div>
            </div>
            <div class="build-price">{{ number_format($build->total_price, 0, ',', ' ') }} ₽</div>
        </div>

        <div class="build-specs">
            @php
                $componentTypes = ['cpu', 'motherboard', 'ram', 'gpu', 'power_supply', 'cpu_cooler', 'case', 'storage'];
            @endphp

            @foreach($componentTypes as $type)
                @php
                    $component = $build->loadedComponents[$type] ?? null;
                @endphp

                @if($component)
                    <div class="spec-item">
                        <div class="component-name">
                            {{ Str::limit($component->name, 40) }}
                            @if($type === 'ram' && isset($component->quantity) && $component->quantity > 1)
                                <small class="text-gray-text">×{{ $component->quantity }}</small>
                            @endif
                        </div>
                        <div class="component-price">
                            @if($type === 'ram' && isset($component->quantity) && $component->quantity > 1)
                                {{ number_format(($component->price ?? 0) * $component->quantity, 0, ',', ' ') }} ₽
                            @else
                                {{ number_format($component->price ?? 0, 0, ',', ' ') }} ₽
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="build-actions">
            <div class="build-actions-left">
                <button class="btn btn-outline btn-sm view-links-btn" data-build-id="{{ $build->id }}">
                    <span class="icon icon-link"></span> Ссылки на комплектующие
                </button>
                <a href="{{ route('build.pdf', $build) }}" class="btn btn-outline btn-sm" target="_blank">
                    <span class="icon icon-download"></span> PDF
                </a>
            </div>
            <form action="{{ route('build.destroy', $build) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-accent btn-sm" onclick="return confirm('Удалить сборку?')">
                    <span class="icon icon-trash"></span> Удалить
                </button>
            </form>
        </div>
    </div>
@empty
    <div class="text-center py-5">
        <span class="icon icon-tools" style="font-size: 3rem;"></span>
        <p class="text-gray-text">У вас пока нет сохранённых сборок</p>
    </div>
@endforelse