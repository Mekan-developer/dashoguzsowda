#!/bin/sh
# Прод-entrypoint для app / horizon / reverb / scheduler.
# Миграции выполняет только один контейнер — тот, где RUN_MIGRATIONS=true (app).
set -e

cd /var/www/dzsowda

if [ ! -f .env ]; then
    echo "FATAL: /var/www/dzsowda/.env не смонтирован. См. docs/DEPLOY.md" >&2
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "FATAL: APP_KEY не задан в .env (php artisan key:generate --show)" >&2
    exit 1
fi

# Ждём БД. depends_on: service_healthy покрывает старт с нуля, но не рестарт
# mysql под нагрузкой, поэтому цикл оставлен.
# Креды читаются из .env через phpdotenv, а не через getenv(): .env монтируется
# файлом, переменных окружения в контейнере нет.
echo "[entrypoint] ожидание базы данных..."
i=0
until php -r '
    require "/var/www/dzsowda/vendor/autoload.php";
    Dotenv\Dotenv::createImmutable("/var/www/dzsowda")->safeLoad();
    $dsn = sprintf("mysql:host=%s;port=%s", $_ENV["DB_HOST"] ?? "mysql", $_ENV["DB_PORT"] ?? 3306);
    try { new PDO($dsn, $_ENV["DB_USERNAME"] ?? "", $_ENV["DB_PASSWORD"] ?? ""); exit(0); }
    catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "FATAL: база данных недоступна после 60 попыток" >&2
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] база данных доступна"

# Манифест пакетов не собирается на этапе build (composer --no-scripts),
# т.к. package:discover требует загруженного приложения и .env.
php artisan package:discover --ansi

# Символическая ссылка public/storage → storage/app/public (том с загрузками).
php artisan storage:link --force >/dev/null 2>&1 || true

echo "[entrypoint] очистка старых кэшей..."
php artisan optimize:clear

echo "[entrypoint] прогрев кэшей..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
