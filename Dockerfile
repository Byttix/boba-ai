FROM php:8.2-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libssl-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создание рабочей директории
WORKDIR /var/www/html

# Копирование файлов
COPY . .

# Установка прав
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Установка зависимостей Laravel
RUN composer install --no-dev --optimize-autoloader

# Генерация ключа приложения
RUN php artisan key:generate --force

# Оптимизация
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
