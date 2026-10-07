#!/bin/sh
# Прод-entrypoint для app / horizon / reverb / scheduler / artisan.
# Переменные приходят из .env.production через env_file — файла .env
# в контейнере нет, Laravel читает окружение.
set -e

cd /var/www/dzsowda

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY не задан в .env.production (см. docs/DEPLOY.md)" >&2
    exit 1
fi

# Каталоги storage приезжают из образа в момент создания тома, но том
# переживает пересборку и о новых подпапках не узнает
mkdir -p storage/app/public storage/framework/cache/data \
         storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache

# Манифест пакетов не собирается в образе (composer --no-scripts):
# package:discover поднимает приложение, а ему нужно окружение
php artisan package:discover --ansi >/dev/null

# Схему догоняет только app (RUN_MIGRATIONS=true в docker-compose.prod.yml):
# параллельный migrate из нескольких контейнеров ловил бы блокировки.
# depends_on ждёт healthcheck mysql, но после рестарта под нагрузкой база
# может отвечать не сразу — отсюда повторы.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    attempt=1
    until php artisan migrate --force --no-interaction; do
        if [ "$attempt" -ge 10 ]; then
            echo "FATAL: миграции не прошли после 10 попыток" >&2
            exit 1
        fi
        echo "[entrypoint] база не отвечает, попытка $attempt из 10, повтор через 3 с"
        attempt=$((attempt + 1))
        sleep 3
    done
fi

# Кэши — на старте, а не в образе: зависят от переменных окружения.
# В dev код примонтирован с хоста и кэш только мешает (OPTIMIZE_ON_BOOT=false
# в docker-compose.override.yml)
if [ "${OPTIMIZE_ON_BOOT:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
else
    php artisan optimize:clear >/dev/null
fi

exec docker-php-entrypoint "$@"
