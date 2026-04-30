/**
 * PC Builder AI - Основной JavaScript файл
 */

// Глобальное состояние
window.PCBuilder = {
    currentBuild: null,
    selectedComponents: {},
    budget: 80000,
    useCase: 'gaming',
    apiBase: '/api/v1',

    // Инициализация
    init() {
        this.bindEvents();
        this.loadDefaultBuild();
        this.initializeCharts();
    },

    // Привязка событий
    bindEvents() {
        // Бюджет слайдер
        const budgetSlider = document.getElementById('budgetSlider');
        const budgetInput = document.getElementById('budget');

        if (budgetSlider && budgetInput) {
            budgetSlider.addEventListener('input', (e) => {
                budgetInput.value = e.target.value;
                this.updateBudgetDisplay();
            });

            budgetInput.addEventListener('change', (e) => {
                budgetSlider.value = e.target.value;
                this.updateBudgetDisplay();
                this.recommendBuild();
            });
        }

        // Кнопка подбора
        const recommendBtn = document.getElementById('recommendBtn');
        if (recommendBtn) {
            recommendBtn.addEventListener('click', () => this.recommendBuild());
        }

        // Сохранение сборки
        const saveBtn = document.getElementById('saveBuildBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveBuild());
        }
    },

    // Обновление отображения бюджета
    updateBudgetDisplay() {
        const budget = document.getElementById('budget')?.value || this.budget;
        const display = document.getElementById('budgetDisplay');

        if (display) {
            const formatted = new Intl.NumberFormat('ru-RU').format(budget);
            display.textContent = `${formatted} ₽`;
            display.classList.add('pulse');

            setTimeout(() => {
                display.classList.remove('pulse');
            }, 1000);
        }

        this.budget = parseInt(budget);
    },

    // Загрузка дефолтной сборки
    async loadDefaultBuild() {
        this.showLoading();

        try {
            await this.recommendBuild();
        } catch (error) {
            console.error('Failed to load default build:', error);
            this.showError('Не удалось загрузить сборку');
        }
    },

    // Подбор сборки
    async recommendBuild() {
        this.showLoading();

        const data = {
            budget: this.budget,
            use_case: this.useCase,
            cpu_manufacturer: document.getElementById('cpuManufacturer')?.value || null,
            need_gpu: document.getElementById('needGPU')?.checked ?? true,
            preferred_brands: this.getPreferredBrands(),
            rgb_lighting: document.getElementById('rgbLighting')?.checked ?? false
        };

        try {
            const response = await fetch(`${this.apiBase}/recommend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.currentBuild = result;
                this.displayBuild(result);
                this.showResults();
                this.initializeCompatibilityCheck();
            } else {
                throw new Error(result.error || 'Не удалось подобрать сборку');
            }
        } catch (error) {
            console.error('Recommendation error:', error);
            this.showError(error.message);
        }
    },

    // Отображение сборки
    displayBuild(buildData) {
        const container = document.getElementById('componentsList');
        if (!container) return;

        container.innerHTML = '';

        // Создаем карточки компонентов
        for (const [type, component] of Object.entries(buildData.build)) {
            if (!component) continue;

            const card = this.createComponentCard(type, component);
            container.appendChild(card);
        }

        // Обновляем итоговую стоимость
        this.updateTotalPrice(buildData.total_price, buildData.budget_utilization);

        // Обновляем график распределения бюджета
        this.updateBudgetChart(buildData);

        // Сохраняем выбранные компоненты
        this.selectedComponents = buildData.build;
    },

    // Создание карточки компонента
    createComponentCard(type, component) {
        const icons = {
            'cpu': 'fas fa-microchip',
            'motherboard': 'fas fa-microchip',
            'ram': 'fas fa-memory',
            'gpu': 'fas fa-gamepad',
            'psu': 'fas fa-bolt',
            'cooler': 'fas fa-fan',
            'case': 'fas fa-desktop',
            'storage': 'fas fa-hdd'
        };

        const names = {
            'cpu': 'Процессор',
            'motherboard': 'Материнская плата',
            'ram': 'Оперативная память',
            'gpu': 'Видеокарта',
            'psu': 'Блок питания',
            'cooler': 'Кулер',
            'case': 'Корпус',
            'storage': 'Накопитель'
        };

        const col = document.createElement('div');
        col.className = 'col-md-6 col-xl-4 mb-3 fade-in';

        col.innerHTML = `
            <div class="card component-card h-100" data-component-type="${type}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge bg-secondary mb-1">${names[type] || type}</span>
                            <h6 class="card-title mb-1">${component.name}</h6>
                        </div>
                        <i class="${icons[type] || 'fas fa-cube'} fa-lg text-primary"></i>
                    </div>
                    <p class="card-text text-muted small mb-2">
                        ${this.getComponentSpecs(type, component)}
                    </p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-success mb-0">${this.formatPrice(component.price)} ₽</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="PCBuilder.showComponentDetails('${type}', ${component.id})">
                                <i class="fas fa-info-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="PCBuilder.replaceComponent('${type}')">
                                <i class="fas fa-exchange-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        return col;
    },

    // Получение спецификаций компонента
    getComponentSpecs(type, component) {
        const specs = [];

        switch(type) {
            case 'cpu':
                if (component.cores) specs.push(`${component.cores} ядер`);
                if (component.socket) specs.push(`Сокет: ${component.socket}`);
                if (component.tdp) specs.push(`TDP: ${component.tdp}W`);
                break;
            case 'motherboard':
                if (component.socket) specs.push(`Сокет: ${component.socket}`);
                if (component.chipset) specs.push(`Чипсет: ${component.chipset}`);
                if (component.memory_type) specs.push(`Память: ${component.memory_type}`);
                break;
            case 'ram':
                if (component.capacity) specs.push(`${component.capacity}GB`);
                if (component.speed) specs.push(`${component.speed}MHz`);
                if (component.timings) specs.push(`Тайминги: ${component.timings}`);
                break;
            case 'gpu':
                if (component.vram) specs.push(`${component.vram}GB VRAM`);
                if (component.tdp) specs.push(`TDP: ${component.tdp}W`);
                break;
            case 'psu':
                if (component.power_consumption) specs.push(`${component.power_consumption}W`);
                if (component.efficiency_rating) specs.push(component.efficiency_rating);
                break;
            case 'cooler':
                if (component.max_tdp) specs.push(`Макс. TDP: ${component.max_tdp}W`);
                if (component.cooler_type) specs.push(component.cooler_type);
                break;
            case 'case':
                if (component.case_type) specs.push(component.case_type);
                if (component.max_gpu_length) specs.push(`GPU до ${component.max_gpu_length}мм`);
                break;
            case 'storage':
                if (component.capacity) specs.push(`${component.capacity}GB`);
                if (component.interface) specs.push(`Интерфейс: ${component.interface}`);
                break;
        }

        return specs.join(' · ');
    },

    // Форматирование цены
    formatPrice(price) {
        return new Intl.NumberFormat('ru-RU').format(Math.round(price));
    },

    // Обновление итоговой цены
    updateTotalPrice(totalPrice, utilization) {
        const priceElement = document.getElementById('totalPrice');
        const utilizationElement = document.getElementById('budgetUtilization');

        if (priceElement) {
            priceElement.textContent = `${this.formatPrice(totalPrice)} ₽`;
        }

        if (utilizationElement) {
            utilizationElement.textContent = `Использовано ${utilization || 0}% бюджета`;
        }
    },

    // Инициализация графиков
    initializeCharts() {
        // Инициализация Chart.js
        if (typeof Chart !== 'undefined') {
            window.budgetChart = null;
        }
    },

    // Обновление графика бюджета
    updateBudgetChart(buildData) {
        if (typeof Chart === 'undefined' || !buildData.build) return;

        const ctx = document.getElementById('budgetChart')?.getContext('2d');
        if (!ctx) return;

        // Собираем данные для графика
        const labels = [];
        const data = [];
        const backgroundColors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
            '#9966FF', '#FF9F40', '#8AC926', '#1982C4'
        ];

        for (const [type, component] of Object.entries(buildData.build)) {
            if (component && component.price) {
                labels.push(this.getComponentName(type));
                data.push(component.price);
            }
        }

        // Уничтожаем старый график
        if (window.budgetChart) {
            window.budgetChart.destroy();
        }

        // Создаем новый график
        window.budgetChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: backgroundColors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = context.raw;
                                const percentage = ((value / buildData.total_price) * 100).toFixed(1);
                                return `${context.label}: ${this.formatPrice(value)} ₽ (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    },

    // Получение имени компонента
    getComponentName(type) {
        const names = {
            'cpu': 'Процессор',
            'motherboard': 'Мат. плата',
            'ram': 'Память',
            'gpu': 'Видеокарта',
            'psu': 'Блок питания',
            'cooler': 'Кулер',
            'case': 'Корпус',
            'storage': 'Накопитель'
        };

        return names[type] || type;
    },

    // Получение предпочтительных брендов
    getPreferredBrands() {
        const brands = [];
        const checkboxes = document.querySelectorAll('.brand-checkbox:checked');

        checkboxes.forEach(checkbox => {
            brands.push(checkbox.value);
        });

        return brands;
    },

    // Показать детали компонента
    async showComponentDetails(type, componentId) {
        try {
            const response = await fetch(`${this.apiBase}/components/${type}/${componentId}`);
            const result = await response.json();

            if (result.success) {
                this.displayComponentModal(type, result.component);
            }
        } catch (error) {
            console.error('Failed to load component details:', error);
        }
    },

    // Отображение модального окна с деталями компонента
    displayComponentModal(type, component) {
        const modal = new bootstrap.Modal(document.getElementById('componentModal'));
        const title = document.getElementById('componentModalTitle');
        const content = document.getElementById('componentModalContent');
        const link = document.getElementById('componentLink');

        if (title) title.textContent = component.name;
        if (link && component.url) link.href = component.url;

        if (content) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Основные характеристики:</h6>
                        <ul class="list-group list-group-flush">
            `;

            // Добавляем основные характеристики
            for (const [key, value] of Object.entries(component)) {
                if (value && !['id', 'name', 'url', 'price'].includes(key)) {
                    const formattedKey = this.formatKey(key);
                    html += `<li class="list-group-item d-flex justify-content-between">
                        <span>${formattedKey}:</span>
                        <strong>${value}</strong>
                    </li>`;
                }
            }

            html += `
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Цена</h6>
                            <h3 class="text-success">${this.formatPrice(component.price)} ₽</h3>
                            <small class="text-muted">Актуальная цена на момент парсинга</small>
                        </div>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-lightbulb me-2"></i>Советы</h6>
                            <small>${this.getComponentTips(type, component)}</small>
                        </div>
                    </div>
                </div>
            `;

            content.innerHTML = html;
        }

        modal.show();
    },

    // Форматирование ключа для отображения
    formatKey(key) {
        const translations = {
            'socket': 'Сокет',
            'cores': 'Ядра',
            'threads': 'Потоки',
            'tdp': 'TDP (Вт)',
            'chipset': 'Чипсет',
            'form_factor': 'Форм-фактор',
            'memory_type': 'Тип памяти',
            'capacity': 'Объем',
            'speed': 'Скорость',
            'vram': 'Видеопамять',
            'power_consumption': 'Мощность',
            'max_tdp': 'Макс. TDP',
            'cooler_type': 'Тип кулера',
            'case_type': 'Тип корпуса',
            'max_gpu_length': 'Макс. длина GPU',
            'interface': 'Интерфейс'
        };

        return translations[key] || key.replace(/_/g, ' ');
    },

    // Советы по компоненту
    getComponentTips(type, component) {
        const tips = {
            'cpu': 'Для игр важна высокая частота, для работы - много ядер.',
            'gpu': 'VRAM важна для игр в высоком разрешении.',
            'ram': 'DDR5 быстрее DDR4, но дороже.',
            'psu': 'Выбирайте с запасом мощности 20-30%.'
        };

        return tips[type] || 'Выберите совместимый компонент.';
    },

    // Замена компонента
    async replaceComponent(type) {
        const searchQuery = prompt(`Поиск ${this.getComponentName(type)}:`);
        if (!searchQuery) return;

        try {
            const response = await fetch(`${this.apiBase}/components/search?q=${encodeURIComponent(searchQuery)}&type=${type}`);
            const result = await response.json();

            if (result.success && result.results.length > 0) {
                this.showComponentSelectionModal(type, result.results);
            } else {
                alert('Компоненты не найдены');
            }
        } catch (error) {
            console.error('Search error:', error);
            alert('Ошибка поиска');
        }
    },

    // Проверка совместимости
    async checkCompatibility() {
        if (!this.selectedComponents) {
            alert('Сначала подберите сборку');
            return;
        }

        const components = {};
        for (const [type, component] of Object.entries(this.selectedComponents)) {
            components[`${type}_id`] = component.id;
        }

        try {
            const response = await fetch(`${this.apiBase}/compatibility/check`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(components)
            });

            const result = await response.json();
            this.displayCompatibilityResult(result);
        } catch (error) {
            console.error('Compatibility check error:', error);
        }
    },

    // Отображение результата проверки совместимости
    displayCompatibilityResult(result) {
        const container = document.getElementById('compatibilityResult');
        if (!container) return;

        if (result.success) {
            let html = '';

            if (result.compatible) {
                html = `
                    <div class="compatibility-check compatibility-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Совместимо!</strong>
                        <p class="mb-0 small">${result.message}</p>
                    </div>
                `;
            } else {
                html = `
                    <div class="compatibility-check compatibility-error">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>Несовместимо!</strong>
                        <p class="mb-0 small">${result.message}</p>
                `;

                if (result.details && result.details.length > 0) {
                    html += '<ul class="mt-2 mb-0 small">';
                    result.details.forEach(detail => {
                        html += `<li>${detail}</li>`;
                    });
                    html += '</ul>';
                }

                html += '</div>';
            }

            // Информация о питании
            if (result.power_info) {
                html += `
                    <div class="compatibility-check compatibility-warning mt-3">
                        <i class="fas fa-bolt me-2"></i>
                        <strong>Энергопотребление</strong>
                        <p class="mb-0 small">
                            Общее: ${result.power_info.total_power}W<br>
                            Рекомендуемый БП: ${result.power_info.recommended_psu_wattage}W
                        </p>
                    </div>
                `;
            }

            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="compatibility-check compatibility-error">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Ошибка</strong>
                    <p class="mb-0 small">${result.error || 'Неизвестная ошибка'}</p>
                </div>
            `;
        }
    },

    // Сохранение сборки
    async saveBuild() {
        if (!this.currentBuild) {
            alert('Нет данных для сохранения');
            return;
        }

        const name = prompt('Введите название сборки:', 'Моя сборка ПК');
        if (!name) return;

        const components = {};
        for (const [type, component] of Object.entries(this.currentBuild.build)) {
            components[type] = component.id;
        }

        const buildData = {
            name: name,
            components: components,
            total_price: this.currentBuild.total_price,
            use_case: this.useCase,
            is_public: confirm('Сделать сборку публичной?')
        };

        try {
            const response = await fetch(`${this.apiBase}/builds/save`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(buildData)
            });

            const result = await response.json();

            if (result.success) {
                alert('Сборка успешно сохранена!');
            } else {
                alert('Ошибка сохранения: ' + (result.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            alert('Ошибка подключения: ' + error.message);
        }
    },

    // Экспорт сборки
    exportBuild() {
        if (!this.currentBuild) {
            alert('Нет данных для экспорта');
            return;
        }

        const data = {
            ...this.currentBuild,
            exported_at: new Date().toISOString(),
            source: 'PC Builder AI'
        };

        const json = JSON.stringify(data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = `pc-build-${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    },

    // Поделиться сборкой
    shareBuild() {
        if (!this.currentBuild) {
            alert('Нет данных для публикации');
            return;
        }

        const text = `Моя сборка ПК на ${this.formatPrice(this.currentBuild.total_price)} ₽:
Процессор: ${this.currentBuild.build.cpu?.name}
Видеокарта: ${this.currentBuild.build.gpu?.name || 'Встроенная'}

Собрано на PC Builder AI`;

        if (navigator.share) {
            navigator.share({
                title: 'Моя сборка ПК',
                text: text,
                url: window.location.href
            });
        } else {
            navigator.clipboard.writeText(text).then(() => {
                alert('Сборка скопирована в буфер обмена!');
            });
        }
    },

    // Управление состоянием UI
    showLoading() {
        document.getElementById('loadingState')?.classList.remove('d-none');
        document.getElementById('errorState')?.classList.add('d-none');
        document.getElementById('resultsContainer')?.classList.add('d-none');
    },

    showError(message) {
        document.getElementById('loadingState')?.classList.add('d-none');
        document.getElementById('errorState')?.classList.remove('d-none');
        document.getElementById('resultsContainer')?.classList.add('d-none');

        const errorMessage = document.getElementById('errorMessage');
        if (errorMessage) {
            errorMessage.textContent = message;
        }
    },

    showResults() {
        document.getElementById('loadingState')?.classList.add('d-none');
        document.getElementById('errorState')?.classList.add('d-none');
        document.getElementById('resultsContainer')?.classList.remove('d-none');
    }
};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    PCBuilder.init();
});
