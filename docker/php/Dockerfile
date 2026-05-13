FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

COPY . .

RUN composer dump-autoload --optimize

RUN php artisan package:discover --ansi

# =====================================

FROM php:8.3-cli-alpine

WORKDIR /var/www

# Install system dependencies and build tools
RUN apk add --no-cache \
    bash \
    curl \
    unzip \
    git \
    wget \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    linux-headers \
    openssl-dev \
    brotli-dev \
    $PHPIZE_DEPS

# Install PHP extensions (including swoole and redis)
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    bcmath \
    intl \
    pcntl \
    zip \
    && pecl install redis \
    && printf "\n" | pecl install swoole \
    && docker-php-ext-enable redis swoole \
    && apk del $PHPIZE_DEPS

COPY --from=composer /app /var/www

RUN chown -R www-data:www-data /var/www

# Use start script as entrypoint
COPY docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

EXPOSE 8000

CMD ["php", "-v"]