# syntax=docker/dockerfile:1.7

########################################
# 1) Base image with PHP 8.4 + extensions
########################################
FROM php:8.4-fpm-alpine AS base

ARG APP_ENV=prod

ENV APP_ENV=${APP_ENV} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PHP_MEMORY_LIMIT=256M

# System dependencies + common PHP extensions.
RUN set -eux; \
    apk add --no-cache \
        bash \
        git \
        icu-dev \
        icu-data-full \
        libpq-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        postgresql-client \
        unzip; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
        g++ \
        make; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip; \
    pecl install apcu redis; \
    docker-php-ext-enable apcu opcache redis; \
    apk del .build-deps

# Production-ready PHP config.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Composer (pinned major version, latest patch).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app

########################################
# 2) Install dependencies (cacheable)
########################################
FROM base AS vendor

COPY composer.json composer.lock symfony.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

########################################
# 3) Application image
########################################
FROM base AS app

COPY --from=vendor /srv/app/vendor ./vendor

COPY . .

RUN set -eux; \
    mkdir -p var/cache var/log; \
    chmod -R 0777 var; \
    if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    if [ "$APP_ENV" = "prod" ]; then \
        php bin/console cache:warmup --no-debug || true; \
    fi

EXPOSE 9000

CMD ["php-fpm", "-F"]
