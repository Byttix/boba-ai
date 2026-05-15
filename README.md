# BobaAI Builder

Ссылка на сайт - http://boba-ai.online/

Система автоматизированного подбора комплектующих ПК с использованием нейросети.

Пользователь описывает задачу и бюджет в свободной форме, ИИ классифицирует запрос, подбирает совместимые компоненты из базы данных и объясняет выбор каждого из них.

## Возможности

- **ИИ-чат** — общение на естественном языке, распознавание намерений (9 классов) с помощью DistilBERT
- **Подбор сборки** — оптимальная конфигурация по бюджету и назначению (игровой, офисный, рабочая станция, стриминг)
- **Подбор под игру** — автоматическое определение требований по названию игры (Cyberpunk 2077, Minecraft и др.)
- **Фиксация компонента** — пользователь указывает конкретную модель («Хочу ПК с RTX 5060 за 100к»), остальное подбирается под бюджет
- **Апгрейд сборки** — анализ текущей конфигурации и предложение замены с учётом сокета и бюджета
- **Объяснения** — краткое пояснение к каждому выбранному компоненту
- **Сравнение сборок** — таблица отличий между двумя конфигурациями
- **Экспорт в PDF** — скачивание сборки с компонентами и ценами
- **Профиль** — сохранённые сборки, отзывы, редактирование данных

## Стек

| Слой | Технологии |
|------|-----------|
| Backend | Laravel 12, PHP 8.2, SQLite |
| ИИ-микросервис | Python 3, Flask, DistilBERT (distilbert-base-multilingual-cased) |
| База компонентов | SQLite (`pc_builder.db`) |
| База игр | SQLite (`games.db`) |
| Frontend | Blade, CSS, Vanilla JS |

## Требования

- PHP 8.2+, Composer
- Python 3.10+, pip
- Node.js (для сборки ассетов)

## Установка

```bash
# Laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# Python-зависимости
cd python_scripts
python -m venv venv
venv/Scripts/activate  # Windows
source venv/bin/activate  # Linux/Mac
pip install flask flask-cors torch transformers
```

## Запуск

```bash
# Laravel
php artisan serve

# Python-микросервис
LARAVEL_DB_PATH=/path/to/database python python_scripts/neural_microservice.py
```

Микросервис запускается на `http://127.0.0.1:8765`.

## Переменные окружения

В `.env` задаётся подключение к БД и стандартные параметры Laravel.

Для микросервиса на сервере необходимо задать:
```
LARAVEL_DB_PATH=/var/www/laravel/database
```

## Обучение модели

```bash
cd python_scripts
python create_training_data.py   # генерация обучающих данных
python train_distilbert.py       # обучение (5–15 мин на CPU)
```

Обученная модель сохраняется в `python_scripts/models/intent_classifier/`.