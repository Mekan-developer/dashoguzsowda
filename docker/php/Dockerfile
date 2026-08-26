FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
    --prefer-dist --ignore-platform-reqs


# ---**********************------

FROM node:22 AS assets
WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY vite.config.* tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY --from=vendor /build/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

# ---**********************------


FROM php:8.3-fpm AS php

ARG UID=1000
ARG GID=1000

# deb.debian.org is unreachable from the office network, so apt goes through a
# mirror. Override at build time when a direct connection is available:
#   docker compose build --build-arg DEBIAN_MIRROR=https://deb.debian.org php
ARG DEBIAN_MIRROR=https://mirror.yandex.ru
RUN sed -i "s|https\?://deb.debian.org|${DEBIAN_MIRROR}|g" /etc/apt/sources.list.d/debian.sources

# GD (with JPEG/WebP/FreeType) powers PhotoConverter — without it every upload
# silently falls back to storing the original file instead of a WebP.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pcntl pdo_mysql zip exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN groupmod -g ${GID} www-data \
 && usermod  -u ${UID} -g ${GID} www-data

WORKDIR /var/www/dzsowda

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

# Bind-mount с хоста принадлежит чужому UID, а процессы идут от www-data —
# без этого git (и вызывающий его composer) ругается на "dubious ownership".
RUN git config --system --add safe.directory /var/www/dzsowda

COPY --chown=${UID}:${GID} . .
COPY --chown=${UID}:${GID} --from=assets /build/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN mkdir -p storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && chown -R ${UID}:${GID} storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache


 USER www-data


FROM nginx:1.27-alpine AS nginx
COPY docker/nginx/conf.d/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/dzsowda/public
COPY --from=assets /build/public/build /var/www/dzsowda/public/build
RUN ln -sfn ../storage/app/public /var/www/dzsowda/public/storage
