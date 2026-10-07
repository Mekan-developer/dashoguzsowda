# Образы проекта. Стадии:
#   vendor  — composer-зависимости (только сборка)
#   assets  — Vite-бандл админки (только сборка)
#   php     — php-fpm; он же horizon / reverb / scheduler / artisan
#   nginx   — контейнерный nginx со статикой public/
#
# На прод-сервере закрыта часть внешних хостов (deb.debian.org,
# registry.npmjs.org, repo.packagist.org отбиваются), поэтому всё ставится
# через зеркала. Переопределяются из .env.production — см. docs/DEPLOY.md.

# --- vendor -----------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /build

ARG COMPOSER_MIRROR=https://nexus.telecom.tm/repository/composer-proxy/
RUN if [ -n "$COMPOSER_MIRROR" ]; then composer config -g repo.packagist composer "$COMPOSER_MIRROR"; fi

# Сначала только манифесты: слой с зависимостями переживает правки кода.
# Расширений (gd, pcntl…) в образе composer нет — они есть в php-стадии.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --ignore-platform-reqs

# Автозагрузчику нужны только каталоги из autoload в composer.json
COPY app ./app
COPY database ./database
RUN composer dump-autoload --no-dev --optimize --no-scripts


# --- assets -----------------------------------------------------------------
# slim (glibc), а не alpine: в package.json явно прописан
# @rollup/rollup-linux-x64-gnu, на musl npm ci его отвергает
FROM node:22-slim AS assets
WORKDIR /build

ARG NPM_REGISTRY=https://nexus.telecom.tm/repository/npm-proxy/
COPY package.json package-lock.json ./
RUN if [ -n "$NPM_REGISTRY" ]; then npm config set registry "$NPM_REGISTRY"; fi \
 && npm config set fetch-retries 5 \
 && npm config set fetch-retry-maxtimeout 120000 \
 && npm ci --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json ./
COPY resources ./resources
# Админка импортирует ziggy-js прямо из vendor
COPY --from=vendor /build/vendor/tightenco/ziggy ./vendor/tightenco/ziggy

# Вкомпилируются в бандл: адрес WebSocket, по которому браузер админки
# ходит в Reverb. Поменялись — нужна пересборка образа, рестарта мало.
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build


# --- php --------------------------------------------------------------------
FROM php:8.3-fpm AS php

ARG UID=1000
ARG GID=1000
# Каталог Debian целиком (тот, что у deb.debian.org — /debian). Nexus в сети
# telecom.tm в десятки раз быстрее; запасной — https://mirror.yandex.ru/debian
ARG APT_MIRROR=https://nexus.telecom.tm/repository/debian-proxy

RUN rm -f /etc/apt/sources.list.d/debian.sources \
 && printf '%s\n' \
      "deb ${APT_MIRROR} trixie main" \
      "deb ${APT_MIRROR} trixie-updates main" \
      > /etc/apt/sources.list

# Расширения собираются из -dev пакетов, после сборки -dev удаляются, а
# в образе остаются только runtime-библиотеки, с которыми слинкованы .so
# (приём из официальных образов docker-library).
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd pcntl pdo_mysql zip exif opcache; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark > /dev/null; \
    find /usr/local -type f -executable -exec ldd '{}' ';' 2>/dev/null \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search \
        | awk -F': ' '!/^diversion / { print $1 }' | tr ',' '\n' | tr -d ' ' | sort -u \
        | xargs -r apt-mark manual > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

# ffmpeg — обработка роликов (ProcessVideoJob)
RUN apt-get update \
 && apt-get install -y --no-install-recommends ffmpeg \
 && rm -rf /var/lib/apt/lists/*

RUN groupmod -g ${GID} www-data && usermod -u ${UID} -g ${GID} www-data

COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.prod.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/dzsowda

# Только то, что нужно приложению в рантайме. Белый список вместо COPY . .:
# новый каталог в репозитории (документация, мобилка, socket-server) сам
# в образ не попадёт.
COPY --chown=${UID}:${GID} artisan composer.json ./
COPY --chown=${UID}:${GID} app ./app
COPY --chown=${UID}:${GID} bootstrap ./bootstrap
COPY --chown=${UID}:${GID} config ./config
COPY --chown=${UID}:${GID} database ./database
COPY --chown=${UID}:${GID} lang ./lang
COPY --chown=${UID}:${GID} public ./public
COPY --chown=${UID}:${GID} resources/views ./resources/views
COPY --chown=${UID}:${GID} routes ./routes
COPY --chown=${UID}:${GID} --from=vendor /build/vendor ./vendor
COPY --chown=${UID}:${GID} --from=assets /build/public/build ./public/build

# storage целиком — тома (storage_app, storage_logs), здесь только каркас:
# том при первом создании наследует владельца из образа
RUN mkdir -p storage/app/public storage/framework/cache/data \
             storage/framework/sessions storage/framework/views \
             storage/logs bootstrap/cache \
 && chown -R ${UID}:${GID} storage bootstrap/cache \
 && chmod +x /usr/local/bin/entrypoint.sh

USER www-data
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]


# --- nginx ------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/conf.d/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=php /var/www/dzsowda/public /var/www/dzsowda/public
# Загрузки пользователей лежат в томе storage_app, примонтированном сюда же
RUN ln -sfn ../storage/app/public /var/www/dzsowda/public/storage
