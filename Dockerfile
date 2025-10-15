FROM php:8.4.13-fpm

# Рабочая директория
WORKDIR /var/www

# Установка системных зависимостей (включая PostgreSQL)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка PHP-расширений
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копирование кода приложения
COPY . /var/www

# Копирование .env (если отсутствует — пропустить)
RUN cp .env.example .env || true

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Создание SQLite файла (если используется)
RUN mkdir -p /var/www/database && touch /var/www/database/database.sqlite

# Генерация ключа приложения (если Laravel)
RUN php artisan key:generate || true

# Применение миграций
RUN php artisan migrate --force || true

# Открываем порт
EXPOSE 8000

# Запуск сервера Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
