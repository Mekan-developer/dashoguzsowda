# Контекст сборки — корень проекта (не ./docker/php), см. docker-compose*.yml.
#
# Стадии:
#   base   — PHP 8.3 + расширения + ffmpeg. Общая для dev и prod.
#   dev    — base + UID хоста, код монтируется томом (docker-compose.yml).
#   vendor — composer install --no-dev, только для prod.
#   assets — npm run build, только для prod.
#   prod   — base + код + vendor + public/build внутри образа.
#   web    — nginx с public/ из prod-образа.

# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS base

# Зеркало репозиториев Alpine. Пусто — официальный dl-cdn.alpinelinux.org.
# Задать, если официальный CDN недоступен из сети сборки, например:
#   ALPINE_MIRROR=https://mirror.yandex.ru/mirrors/alpine
ARG ALPINE_MIRROR=""
RUN if [ -n "${ALPINE_MIRROR}" ]; then \
      sed -i "s|https://dl-cdn.alpinelinux.org/alpine|${ALPINE_MIRROR}|g" /etc/apk/repositories; \
    fi

# Системные зависимости.
# ffmpeg — обязателен: ProcessVideoJob и FfprobeVideoProbe вызывают ffmpeg/ffprobe.
# libwebp-dev — обязателен: без него gd собирается без imagewebp(), и WebpEncoder
# в ProcessListingImagesJob / ImageConversionService падает на всех фото.
RUN apk add --no-cache \
    bash git curl zip unzip ffmpeg \
    libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev \
    libzip-dev icu-dev oniguruma-dev

# Версия расширения redis при сборке из исходников.
ARG PHPREDIS_VERSION=6.1.0

# PHP-расширения. Инструменты сборки ставятся и удаляются в одном слое.
#
# Расширение redis добывается по цепочке источников — в закрытых сетях
# доступен обычно не первый:
#   1. GitHub (сборка из исходников)
#   2. pecl.php.net
#   3. готовый php83-pecl-redis из репозитория Alpine (то самое зеркало,
#      с которого ставились остальные пакеты)
# Результат в любом случае проверяется через `php -m`: если расширение не
# загрузилось, сборка падает здесь, а не в рантайме.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps autoconf g++ make linux-headers; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
      pdo_mysql mysqli mbstring exif pcntl bcmath gd zip intl opcache sockets; \
    \
    if git clone --depth 1 --branch "${PHPREDIS_VERSION}" \
         https://github.com/phpredis/phpredis.git /tmp/phpredis; then \
      echo "[redis] источник: GitHub"; \
      cd /tmp/phpredis && phpize && ./configure && make -j"$(nproc)" && make install; \
      cd / && rm -rf /tmp/phpredis; \
    elif pecl install redis; then \
      echo "[redis] источник: pecl"; \
      pecl clear-cache; \
    else \
      echo "[redis] GitHub и pecl недоступны — берём готовое расширение из репозитория Alpine"; \
      apk add --no-cache --virtual .redis-ext php83-pecl-redis; \
      cp /usr/lib/php83/modules/redis.so "$(php-config --extension-dir)/"; \
      apk del .redis-ext; \
    fi; \
    \
    docker-php-ext-enable redis; \
    php -m | grep -qx redis; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# ---------------------------------------------------------------------------
FROM base AS dev

# Совпадение UID с хостом, чтобы не ловить права на storage/ при bind-mount.
ARG UID=1000
ARG GID=1000
RUN deluser www-data 2>/dev/null || true \
 && addgroup -g ${GID} www-data \
 && adduser -u ${UID} -G www-data -s /bin/sh -D www-data

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM base AS vendor

# Зеркало Composer. Пусто — официальный repo.packagist.org. Задать, если он
# недоступен из сети сборки, например:
#   COMPOSER_MIRROR=https://mirrors.aliyun.com/composer/
ARG COMPOSER_MIRROR=""
RUN if [ -n "${COMPOSER_MIRROR}" ]; then \
      composer config -g repo.packagist composer "${COMPOSER_MIRROR}"; \
    fi

COPY composer.json composer.lock ./

# --no-scripts: package:discover требует загруженного приложения, его выполняет
# entrypoint уже в рантайме, когда доступен .env.
RUN composer install \
    --no-dev --no-scripts --no-autoloader \
    --prefer-dist --no-interaction --no-progress

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------------
# Образ на glibc, а не alpine: в package.json жёстко закреплён
# @rollup/rollup-linux-x64-gnu, на musl он не запустится.
FROM node:20-bookworm-slim AS assets

WORKDIR /app

COPY . .

# resources/js/app.js импортирует ZiggyVue из ../../vendor/tightenco/ziggy —
# без vendor сборка не резолвит этот путь.
COPY --from=vendor /var/www/vendor ./vendor

# VITE_* переменные вкомпилируются в бандл на этапе сборки — в рантайме их
# поменять уже нельзя. Хост .env исключён через .dockerignore, поэтому нужный
# .env для Vite собираем здесь из build-аргументов.
ARG VITE_APP_NAME="Daşoguz söwda"
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

# Зеркало/прокси npm-реестра. Пусто — официальный registry.npmjs.org.
# npm 9+ сам переписывает на него ссылки registry.npmjs.org из package-lock.json
# (replace-registry-host=npmjs по умолчанию), правки lock-файла не нужны.
ARG NPM_REGISTRY=""

# Если public/build уже собран заранее и попал в контекст — npm не трогаем.
# Это единственный рабочий путь в сети, где заблокированы npm-реестры:
# ассеты собираются там, где есть node_modules, и коммитятся в репозиторий.
RUN printf '%s\n' \
    "VITE_APP_NAME=${VITE_APP_NAME}" \
    "VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}" \
    "VITE_REVERB_HOST=${VITE_REVERB_HOST}" \
    "VITE_REVERB_PORT=${VITE_REVERB_PORT}" \
    "VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}" \
    > .env \
 && if [ -f public/build/manifest.json ]; then \
      echo "[assets] используется заранее собранный public/build, npm пропущен"; \
    else \
      echo "[assets] public/build не найден — собираем через npm"; \
      if [ -n "${NPM_REGISTRY}" ]; then npm config set registry "${NPM_REGISTRY}"; fi; \
      npm ci --no-audit --no-fund && npm run build; \
    fi

# ---------------------------------------------------------------------------
FROM base AS prod

COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.prod.conf /usr/local/etc/php-fpm.d/zz-www.conf

# Стабильный UID вместо UID хоста: код внутри образа, bind-mount кода нет.
RUN deluser www-data 2>/dev/null || true \
 && addgroup -g 1000 www-data \
 && adduser -u 1000 -G www-data -s /bin/sh -D www-data

COPY --chown=www-data:www-data . /var/www
COPY --from=vendor --chown=www-data:www-data /var/www/vendor /var/www/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/public/build

COPY --chmod=0755 docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint

# Каталоги, которые Laravel пишет в рантайме. app/public и logs монтируются
# named-томами (см. docker-compose.prod.yml) — здесь создаётся их скелет.
RUN set -eux; \
    mkdir -p \
      storage/app/public \
      storage/app/private \
      storage/app/firebase \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache; \
    rm -f .env; \
    ln -sfn /var/www/storage/app/public public/storage; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R u+rwX storage bootstrap/cache

USER www-data

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

# nginx отдаёт статику сам, поэтому ему нужен public/ — берём из prod-образа,
# а не через общий том с app-контейнером.
COPY --from=prod /var/www/public /var/www/public
COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf
