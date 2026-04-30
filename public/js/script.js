// =================== ПРОСТОЙ И РАБОТАЮЩИЙ СКРИПТ ===================
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM загружен');

    // =================== УТИЛИТЫ ===================
    function formatPrice(price) {
        return parseInt(price).toLocaleString('ru-RU') + ' ₽';
    }

    function formatRelativeDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'только что';
        if (diffMins < 60) return `${diffMins} мин назад`;
        if (diffMins < 1440) return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });

        return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' });
    }

    function formatShortDate(dateString) {
        const date = new Date(dateString);
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const year = date.getFullYear();
        return `${month}.${day}.${year}`;
    }

    function getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div style="display: flex; align-items: center;">
                <span style="font-size: 1.2rem; margin-right: 10px;">${type === 'success' ? '✅' : '❌'}</span>
                <span>${message}</span>
            </div>
        `;

        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            max-width: 400px;
            background: ${type === 'success' ? '#3BDA00' : '#F10026'};
            border-left: 4px solid ${type === 'success' ? '#268E00' : '#9D0019'};
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // =================== ФИКСИРОВАННАЯ ВЫСОТА ЧАТА С ПРАВИЛЬНЫМ СКРОЛЛОМ ===================
    function setupFixedChatHeight() {
        const chatMessages = document.getElementById('chatMessages');
        const chatContainer = document.querySelector('.chat-messages-container');
        const chatInputContainer = document.querySelector('.chat-input-container');

        if (!chatMessages || !chatContainer || !chatInputContainer) return;

        // Устанавливаем фиксированную высоту для контейнера чата
        function setChatHeight() {
            const isMobile = window.innerWidth <= 768;
            const viewportHeight = window.innerHeight;

            if (isMobile) {
                // На мобильных: 70% высоты экрана
                const chatHeight = Math.min(viewportHeight * 0.7, 600);
                chatContainer.style.height = chatHeight + 'px';
                chatMessages.style.height = '100%';
                chatMessages.style.maxHeight = chatHeight + 'px';
                chatMessages.style.overflowY = 'auto'; // Добавляем скролл
            } else {
                // На десктопе: фиксированная высота 650px
                chatContainer.style.height = '650px';
                chatMessages.style.height = '100%';
                chatMessages.style.maxHeight = '650px';
                chatMessages.style.overflowY = 'auto'; // Добавляем скролл
            }

            // Убедимся, что у контейнера правильные стили
            chatContainer.style.display = 'flex';
            chatContainer.style.flexDirection = 'column';
            chatContainer.style.overflow = 'hidden';

            // Проверяем, нужно ли показывать скроллбар
            const shouldShowScrollbar = chatMessages.scrollHeight > chatMessages.clientHeight;
            if (shouldShowScrollbar) {
                chatMessages.style.paddingRight = '10px'; // Добавляем отступ для скроллбара
            } else {
                chatMessages.style.paddingRight = '20px'; // Стандартный отступ
            }
        }

        // Вызываем сразу и при изменении размера окна
        setChatHeight();
        window.addEventListener('resize', setChatHeight);

        // Автоматический скролл вниз при загрузке
        setTimeout(() => {
            if (chatMessages.scrollHeight > chatMessages.clientHeight) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }, 300);

        // Наблюдатель за новыми сообщениями
        const observer = new MutationObserver(() => {
            setTimeout(() => {
                // Обновляем высоту и проверяем скролл
                setChatHeight();

                // Прокручиваем вниз к новым сообщениям
                if (chatMessages.scrollHeight > chatMessages.clientHeight) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            }, 100);
        });

        observer.observe(chatMessages, {
            childList: true,
            subtree: true
        });

        console.log('Высота чата установлена фиксированной со скроллом');
    }

    // =================== МОДАЛЬНЫЕ ОКНА ===================
    function initModals() {
        console.log('Инициализация модальных окон');

        // Закрытие по крестику
        document.querySelectorAll('.modal .close').forEach(closeBtn => {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });

        // Закрытие по клику вне окна
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                });
            }
        });

        // Открытие модального окна редактирования профиля
        const editProfileBtn = document.getElementById('editProfileBtn');
        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = document.getElementById('editProfileModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            });
        }
    }

    // =================== КОМПОНЕНТЫ ===================
    function initComponents() {
        console.log('Инициализация компонентов, кнопок:', document.querySelectorAll('.view-links-btn').length);

        document.querySelectorAll('.view-links-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();

                const buildId = this.getAttribute('data-build-id');
                console.log('Открытие компонентов для сборки:', buildId);

                // Открываем модальное окно
                const modal = document.getElementById('componentsModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';

                    // Показываем загрузку
                    const componentsList = document.getElementById('componentsList');
                    if (componentsList) {
                        componentsList.innerHTML = `
                            <div style="text-align: center; padding: 40px;">
                                <div style="font-size: 2rem; margin-bottom: 10px;">⏳</div>
                                <p>Загрузка данных...</p>
                            </div>
                        `;
                    }

                    // Загружаем данные
                    try {
                        const response = await fetch(`/api/builds/${buildId}/links`);
                        console.log('Ответ от API:', response.status);

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const data = await response.json();
                        console.log('Данные от API:', data);

                        if (data.success) {
                            renderComponents(data);
                        } else {
                            showError(data.error || 'Ошибка загрузки данных');
                        }
                    } catch (error) {
                        console.error('Ошибка загрузки компонентов:', error);
                        showError('Ошибка загрузки данных: ' + error.message);
                    }
                }
            });
        });
    }

    function renderComponents(data) {
        console.log('Рендерим компоненты:', data);

        const { build, components } = data;
        const buildName = document.getElementById('buildName');
        const buildDate = document.getElementById('buildDate');
        const buildTotalPrice = document.getElementById('buildTotalPrice');
        const componentsList = document.getElementById('componentsList');

        if (!buildName || !buildDate || !buildTotalPrice || !componentsList) {
            console.error('Не найдены элементы для отображения данных');
            return;
        }

        buildName.textContent = build.name;
        buildDate.textContent = `Создано: ${formatShortDate(build.created_at)}`; // ТОЛЬКО ДАТА, БЕЗ ВРЕМЕНИ
        buildTotalPrice.textContent = formatPrice(build.total_price);

        const typeNames = {
            'cpu': 'Процессор',
            'motherboard': 'Материнская плата',
            'ram': 'Оперативная память',
            'gpu': 'Видеокарта',
            'power_supply': 'Блок питания',
            'cpu_cooler': 'Кулер процессора',
            'case': 'Корпус',
            'storage': 'Накопитель'
        };

        let componentsHtml = '';
        let hasComponents = false;

        if (components && typeof components === 'object') {
            Object.entries(components).forEach(([type, component]) => {
                if (component && component.name) {
                    hasComponents = true;

                    const typeName = typeNames[type] || type;
                    let componentName = component.name;
                    let componentPrice = component.price || 0;

                    if (type === 'ram' && component.quantity) {
                        componentPrice = (component.price || 0) * component.quantity;
                        componentName += ` ×${component.quantity}`;
                    }

                    componentsHtml += `
                        <div class="component-item">
                            <div class="component-info">
                                <div class="component-name">${typeName}: ${componentName}</div>
                                <div class="component-price">${formatPrice(componentPrice)}</div>
                            </div>
                            <div>
                                ${component.url ?
                        `<a href="${component.url}" target="_blank" class="citilink-btn">
                                        🛒 ${component.url.includes('citilink.ru') ? 'Купить в Ситилинк' : 'Перейти к товару'}
                                    </a>` :
                        '<span style="color: var(--gray-text);">Ссылка отсутствует</span>'
                    }
                            </div>
                        </div>
                    `;
                }
            });
        }

        if (!hasComponents) {
            componentsHtml = '<div class="no-links">Компоненты не найдены</div>';
        }

        componentsList.innerHTML = componentsHtml;
    }

    function showError(message) {
        const componentsList = document.getElementById('componentsList');
        if (componentsList) {
            componentsList.innerHTML = `
                <div style="color: #F10026; padding: 20px; text-align: center;">
                    <div style="font-size: 2rem;">❌</div>
                    <p>${message}</p>
                    <button onclick="location.reload()" class="btn btn-outline" style="margin-top: 10px;">
                        Обновить страницу
                    </button>
                </div>
            `;
        }
    }

    // =================== РЕДАКТИРОВАНИЕ ПРОФИЛЯ ===================
    function initProfile() {
        console.log('Инициализация профиля');

        const editProfileForm = document.getElementById('editProfileForm');
        if (editProfileForm) {
            editProfileForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const saveBtn = document.getElementById('saveProfileBtn');
                const originalText = saveBtn.innerHTML;

                try {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<span class="spinner"></span> Сохранение...';

                    const formData = new FormData(this);
                    formData.append('_method', 'PUT');

                    const response = await fetch('/profile', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': getCSRFToken(),
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (response.ok) {
                        showNotification('Профиль успешно обновлен!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.errors || data.message || 'Ошибка обновления', 'error');
                    }
                } catch (error) {
                    console.error('Ошибка при сохранении профиля:', error);
                    showNotification('Ошибка соединения', 'error');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                }
            });
        }

        // Валидация паролей
        const passwordField = document.getElementById('editPassword');
        const confirmField = document.getElementById('editPasswordConfirmation');

        if (passwordField && confirmField) {
            const validatePasswords = function() {
                const password = passwordField.value;
                const confirm = confirmField.value;

                if (password && confirm && password !== confirm) {
                    confirmField.style.borderColor = '#F10026';
                    return false;
                } else {
                    confirmField.style.borderColor = '#1e2a44';
                    return true;
                }
            };

            passwordField.addEventListener('input', validatePasswords);
            confirmField.addEventListener('input', validatePasswords);
        }
    }

    // =================== ЧАТ (если есть на странице) ===================
    function initChat() {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            console.log('Инициализация чата');

            setupFixedChatHeight();

            // Авто-высота textarea
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                });

                // Фокус на поле ввода
                setTimeout(() => {
                    messageInput.focus();
                }, 500);

                // Ctrl+Enter для отправки
                messageInput.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'Enter') {
                        e.preventDefault();
                        const form = this.closest('form');
                        if (form) form.submit();
                    }
                });
            }

            function updateMessageTimes() {
                document.querySelectorAll('.message-time[data-timestamp]').forEach(element => {
                    const timestamp = parseInt(element.getAttribute('data-timestamp'));
                    element.textContent = formatRelativeDate(new Date(timestamp).toISOString());
                });
            }

            updateMessageTimes();
            setInterval(updateMessageTimes, 60000);
        }
    }

    // =================== АНИМАЦИИ И ЭФФЕКТЫ ===================
    function initAnimations() {
        // Карточки с эффектом поднятия
        const cards = document.querySelectorAll('.feature-card, .build-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                if (window.innerWidth > 768) {
                    card.style.transform = 'translateY(-5px)';
                }
            });

            card.addEventListener('mouseleave', () => {
                if (window.innerWidth > 768) {
                    card.style.transform = 'translateY(0)';
                }
            });
        });

        // Исправление для iOS
        if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
            document.body.style.webkitOverflowScrolling = 'touch';
        }
    }

    // =================== ОБРАБОТЧИКИ ГЛОБАЛЬНЫХ СОБЫТИЙ ===================
    function initGlobalHandlers() {
        // Глобальные функции для вызова из HTML
        window.closeComponentsModal = function() {
            const modal = document.getElementById('componentsModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        };

        window.closeEditProfileModal = function() {
            const modal = document.getElementById('editProfileModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        };

        window.showComponentLinks = function(buildId) {
            const btn = document.querySelector(`.view-links-btn[data-build-id="${buildId}"]`);
            if (btn) btn.click();
        };

        // Flash сообщения
        const flashMessage = document.getElementById('flash-message');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.style.opacity = '0';
                setTimeout(() => flashMessage.remove(), 300);
            }, 5000);
        }
    }

    // =================== ИНИЦИАЛИЗАЦИЯ ВСЕГО ===================
    function initAll() {
        console.log('Начало инициализации приложения');

        try {
            initModals();
            initComponents();
            initProfile();
            initChat();
            initAnimations();
            initGlobalHandlers();

            console.log('Приложение успешно инициализировано');
        } catch (error) {
            console.error('Ошибка при инициализации приложения:', error);
            showNotification('Ошибка загрузки скриптов', 'error');
        }
    }

    // Запускаем через небольшую задержку
    setTimeout(initAll, 100);
});

const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
        margin-right: 8px;
    }

    /* ВОССТАНАВЛИВАЕМ СКРОЛЛБАР В ЧАТЕ */
    .chat-messages {
        overflow-y: auto !important;
        overflow-x: hidden !important;
        scrollbar-width: thin;
        scrollbar-color: #3BDA00 rgba(255, 255, 255, 0.05);
    }

    .chat-messages::-webkit-scrollbar {
        width: 8px !important;
    }

    .chat-messages::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05) !important;
        border-radius: 4px !important;
    }

    .chat-messages::-webkit-scrollbar-thumb {
        background: #3BDA00 !important;
        border-radius: 4px !important;
    }

    .chat-messages::-webkit-scrollbar-thumb:hover {
        background: #268E00 !important;
    }

    /* Обеспечиваем видимость скроллбара всегда, даже если контента мало */
    .chat-messages-container {
        overflow: hidden !important;
    }

    .chat-active-container {
        overflow: hidden !important;
    }
`;
document.head.appendChild(style);
