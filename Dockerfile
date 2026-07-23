# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# base — PHP 8.4 FPM with the extension set required by the approved
# architecture (SOLUTION_ARCHITECTURE_AND_TECH_STACK_V0.1.md §7).
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

# Runtime libraries. Installed separately from the build toolchain so they
# survive the removal of the virtual .build-deps package below.
RUN apk add --no-cache \
        bash \
        freetype \
        icu-data-full \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libzip \
        mariadb-client \
        tzdata

RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        linux-headers; \
    docker-php-ext-configure intl; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        sockets \
        zip; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    pecl clear-cache; \
    rm -rf /tmp/pear; \
    apk del --no-network .build-deps; \
    php -m

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zzz-opcache.ini
COPY docker/php/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zzz-app.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# dev — local development target.
#
# The project is bind-mounted at /var/www/html by compose.yaml and `vendor/`
# is rebuilt inside the container (DEC-031), so no application code is baked
# into this image.
# ---------------------------------------------------------------------------
FROM base AS dev

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN apk add --no-cache git unzip
