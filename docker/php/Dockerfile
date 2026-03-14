FROM composer:2 AS composer

FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        git \
        libicu-dev \
        libldap2-dev \
        libonig-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install \
        bcmath \
        intl \
        ldap \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/pymesec-entrypoint
COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN chmod +x /usr/local/bin/pymesec-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/pymesec-entrypoint"]
CMD ["apache2-foreground"]
