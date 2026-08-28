FROM composer:2 AS vendor
WORKDIR /build

ARG COMPOSER_MIRROR=https://nexus.telecom.tm/repository/composer-proxy/
# -g (global), а не в composer.json проекта — иначе меняем content-hash,
# который composer.lock сверяет при install.
RUN if [ -n "$COMPOSER_MIRROR" ]; then composer config -g repo.packagist composer "$COMPOSER_MIRROR"; fi

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
    --prefer-dist --ignore-platform-reqs


# ---**********************------

FROM node:22 AS assets
WORKDIR /build

ARG NPM_REGISTRY=https://nexus.telecom.tm/repository/npm-proxy/
RUN if [ -n "$NPM_REGISTRY" ]; then npm config set registry "$NPM_REGISTRY"; fi

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

ARG APT_MIRROR=https://nexus.telecom.tm/repository/debian-proxy

RUN rm -f /etc/apt/sources.list.d/debian.sources \
 && printf '%s\n' \
      "deb ${APT_MIRROR} trixie main contrib non-free non-free-firmware" \
      "deb ${APT_MIRROR} trixie-updates main contrib non-free non-free-firmware" \
      > /etc/apt/sources.list \
 && cat /etc/apt/sources.list

# GD (with JPEG/WebP/FreeType) powers PhotoConverter — without it every upload
# silently falls back to storing the original file instead of a WebP.
    RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    ffmpeg \
    git \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pcntl pdo_mysql zip exif \
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


COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.prod.conf /usr/local/etc/php-fpm.d/www.conf


USER www-data


FROM nginx:1.27-alpine AS nginx
COPY docker/nginx/conf.d/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/dzsowda/public
COPY --from=assets /build/public/build /var/www/dzsowda/public/build
RUN ln -sfn ../storage/app/public /var/www/dzsowda/public/storage
