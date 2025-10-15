# ---------------- STAGE: build ----------------
FROM php:8.4.13-fpm AS build

# Рабочая директория
WORKDIR /var/www

# Устанавливаем инструменты для сборки и зависимости для расширений
RUN apt-get update -y \
    && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev \
    postgresql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Конфигурация и сборка PHP-расширений
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    mbstring exif pcntl bcmath zip \
    pdo pdo_mysql pdo_sqlite pdo_pgsql gd pgsql

# Redis (PECL) — опционально, но часто полезно
RUN set -eux \
    && pecl install redis || true \
    && docker-php-ext-enable redis || true

# Composer (взять из официального образа)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем только composer-файлы, устанавливаем зависимости (быстрее билды)
WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --no-scripts || true

# Копируем весь проект
COPY . .

# Скопировать .env если нет — безопасно
RUN cp .env.example .env || true

# Права и подготовка каталогов
RUN mkdir -p /var/www/database \
    && touch /var/www/database/database.sqlite \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache || true

# Генерация ключа и кеширование конфигураций (если Laravel)
RUN php artisan key:generate || true \
    && php artisan config:cache || true \
    && php artisan route:cache || true

# ---------------- STAGE: runner ----------------
FROM php:8.4.13-fpm AS runner

WORKDIR /var/www

# Устанавливаем минимальные runtime-зависимости (только то, что нужно для запуска)
RUN apt-get update -y \
    && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Включаем необходимые расширения в runtime (повторно собираем необходимые расширения)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    mbstring exif pcntl bcmath zip \
    pdo pdo_mysql pdo_sqlite pdo_pgsql gd pgsql

# Redis (PECL) в runtime, если нужен
RUN set -eux \
    && pecl install redis || true \
    && docker-php-ext-enable redis || true

# Копируем артефакты из build-stage
COPY --from=build /var/www /var/www
COPY --from=build /usr/bin/composer /usr/bin/composer

# Права
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache || true

# Порт приложения (как в вашем исходном Dockerfile)
EXPOSE 8000

# Defaults — запускаем встроенный сервер Laravel (как у вас было).
# В production лучше запускать php-fpm + nginx, или изменить CMD на entrypoint, который выполнит миграции.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
