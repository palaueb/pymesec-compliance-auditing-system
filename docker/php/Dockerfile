FROM composer:2 AS composer

FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        git \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install \
        bcmath \
        intl \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/pymesec-entrypoint

RUN chmod +x /usr/local/bin/pymesec-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/pymesec-entrypoint"]
CMD ["php-fpm"]
