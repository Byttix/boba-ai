@extends('layouts.app')

@section('title', 'BobaAI Builder - Профиль')

@section('content')
    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <span class="icon icon-user"></span>
            </div>

            <h2 class="profile-name">{{ $user->name }}</h2>
            <p class="profile-email">{{ $user->email }}</p>

            <button type="button" class="btn btn-outline" id="editProfileBtn">
                <span class="icon icon-edit"></span> Редактировать профиль
            </button>

            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value">{{ $user->builds()->count() }}</div>
                    <div class="stat-label">Сборок</div>
                </div>

                <div class="stat-item">
                    <div class="stat-value">{{ $chats }}</div>
                    <div class="stat-label">Чатов</div>
                </div>
            </div>
        </div>

        <div class="saved-builds">
            <div class="builds-header">
                <h2>Мои сохраненные сборки</h2>
            </div>

            <div class="builds-grid" id="buildsGrid">
                @include('profile.partials.build-cards', ['builds' => $builds])
            </div>

            @if($builds->hasMorePages())
                <div class="load-more-container" id="loadMoreContainer">
                    <button class="btn btn-outline" id="loadMoreBtn"
                            data-next-page="2"
                            data-url="{{ route('profile') }}">
                        <span class="icon icon-arrow-down"></span> Загрузить ещё
                    </button>
                </div>
            @endif

            <div class="reviews-section">
                <h3>Отзывы о сборках</h3>

                <div class="review-form">
                    <h4>Оставить отзыв</h4>
                    <form action="{{ route('review.add') }}" method="POST">
                        @csrf
                        <select name="build_id" class="form-control" >
                            <option value="">Выберите сборку для отзыва</option>
                            @foreach($user->builds as $build)
                                <option value="{{ $build->id }}">{{ $build->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="title" class="review-textarea" placeholder="Заголовок отзыва" >
                        <textarea name="comment" class="review-textarea" placeholder="Ваш отзыв..." ></textarea>
                        <div class="review-form-footer">
                            <div class="review-rating-selector">
                                <span class="text-gray-text">Оценка:</span>
                                <select name="rating" >
                                    <option value="5">★★★★★ (5)</option>
                                    <option value="4">★★★★☆ (4)</option>
                                    <option value="3">★★★☆☆ (3)</option>
                                    <option value="2">★★☆☆☆ (2)</option>
                                    <option value="1">★☆☆☆☆ (1)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </div>
                    </form>
                </div>

                @forelse($reviews as $review)
                    <div class="review-item">
                        <div class="review-header">
                            <div class="review-author">{{ $review->title }}</div>
                            <div class="review-date">{{ $review->created_at->format('d.m.Y') }}</div>
                        </div>
                        <div class="review-rating">
                            @for($i = 1; $i <= 5; $i++)
                                <span class="icon icon-star {{ $i <= $review->rating ? 'star-filled' : 'star-empty' }}"></span>
                            @endfor
                        </div>
                        <p>{{ $review->comment }}</p>
                        @if($review->build)
                            <small class="text-gray-text">К сборке: {{ $review->build->name }}</small>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-4 text-gray-text">
                        <span class="icon icon-star" style="font-size: 2rem;"></span>
                        <p>У вас пока нет отзывов</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Модальное окно для просмотра ссылок на комплектующие -->
    <div id="componentsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeComponentsModal()">&times;</span>

            <h3>Ссылки на комплектующие</h3>

            <div class="build-info mb-4">
                <div class="build-info-content">
                    <div>
                        <h5 id="buildName"></h5>
                        <small id="buildDate"></small>
                    </div>
                    <div class="build-price-container">
                        <h4 id="buildTotalPrice"></h4>
                        <small>Общая стоимость</small>
                    </div>
                </div>
            </div>

            <div id="componentsList" class="components-list">
                <!-- Компоненты будут загружены сюда -->
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-primary" onclick="closeComponentsModal()">
                    <span class="icon icon-close"></span> Закрыть
                </button>
            </div>
        </div>
    </div>

    <!-- Модальное окно редактирования профиля -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditProfileModal()">&times;</span>
            <h3>Редактирование профиля</h3>

            <form action="{{ route('profile.update') }}" method="POST" id="editProfileForm">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="editName">Имя</label>
                    <input type="text" id="editName" name="name" class="form-control"
                           value="{{ $user->name }}" required>
                    <div class="text-danger" id="nameError"></div>
                </div>

                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" class="form-control"
                           value="{{ $user->email }}" required>
                    <div class="text-danger" id="emailError"></div>
                </div>

                <hr>

                <div class="password-section-title">Смена пароля (необязательно)</div>

                <div class="form-group">
                    <label for="editCurrentPassword">Текущий пароль</label>
                    <input type="password" id="editCurrentPassword" name="current_password"
                           class="form-control" placeholder="Введите текущий пароль">
                    <small>Требуется только при смене пароля</small>
                    <div class="text-danger" id="currentPasswordError"></div>
                </div>

                <div class="form-group">
                    <label for="editPassword">Новый пароль</label>
                    <input type="password" id="editPassword" name="password"
                           class="form-control" placeholder="Минимум 8 символов">
                    <small>Оставьте пустым, если не хотите менять пароль</small>
                    <div class="text-danger" id="passwordError"></div>
                </div>

                <div class="form-group">
                    <label for="editPasswordConfirmation">Подтверждение пароля</label>
                    <input type="password" id="editPasswordConfirmation" name="password_confirmation"
                           class="form-control" placeholder="Повторите новый пароль">
                    <div class="text-danger" id="passwordConfirmationError"></div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeEditProfileModal()">
                        <span class="icon icon-close"></span> Отмена
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                        <span class="icon icon-save"></span> Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('body_end')
    <div class="compare-bar" id="compareBar" style="display:none;">
        <span class="compare-bar-text">Выбрано: <strong id="compareCount">0</strong> из 2</span>
        <a href="#" class="btn btn-primary btn-sm" id="compareBtn">
            <span class="icon icon-compare"></span> Сравнить
        </a>
        <button class="btn btn-outline btn-sm" id="compareClear">Сбросить</button>
    </div>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Сравнение сборок
            const compareBar = document.getElementById('compareBar');
            const compareCount = document.getElementById('compareCount');
            const compareBtn = document.getElementById('compareBtn');
            const compareClear = document.getElementById('compareClear');

            function updateCompare() {
                const checked = document.querySelectorAll('.compare-checkbox:checked');
                const count = checked.length;
                compareCount.textContent = count;
                compareBar.style.display = count > 0 ? 'flex' : 'none';
                compareBtn.style.opacity = count === 2 ? '1' : '0.5';
                compareBtn.style.pointerEvents = count === 2 ? 'auto' : 'none';

                if (count === 2) {
                    const ids = Array.from(checked).map(cb => cb.value);
                    compareBtn.href = `{{ route('build.compare') }}?ids[]=${ids[0]}&ids[]=${ids[1]}`;
                }

                document.querySelectorAll('.compare-checkbox').forEach(cb => {
                    if (!cb.checked && count >= 2) cb.disabled = true;
                    else cb.disabled = false;
                });
            }

            document.getElementById('buildsGrid').addEventListener('change', function(e) {
                if (e.target.classList.contains('compare-checkbox')) updateCompare();
            });

            compareClear.addEventListener('click', function() {
                document.querySelectorAll('.compare-checkbox').forEach(cb => {
                    cb.checked = false;
                    cb.disabled = false;
                });
                updateCompare();
            });

            const loadMoreBtn = document.getElementById('loadMoreBtn');

            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function() {
                    const btn = this;
                    const nextPage = btn.dataset.nextPage;
                    const url = btn.dataset.url;

                    btn.disabled = true;
                    btn.innerHTML = '<span class="icon icon-spinner"></span> Загрузка...';

                    fetch(`${url}?page=${nextPage}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('buildsGrid').insertAdjacentHTML('beforeend', data.html);

                        if (data.has_more) {
                            btn.dataset.nextPage = data.next_page;
                            btn.disabled = false;
                            btn.innerHTML = '<span class="icon icon-arrow-down"></span> Загрузить ещё';
                        } else {
                            document.getElementById('loadMoreContainer').remove();
                        }
                    })
                    .catch(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="icon icon-arrow-down"></span> Загрузить ещё';
                    });
                });
            }
        });
    </script>
@endpush
