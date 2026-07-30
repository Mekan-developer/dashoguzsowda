# Деплой на Ubuntu-сервер

Схема: **Caddy** (80/443, TLS) → **nginx** (статика + маршрутизация) →
**php-fpm** / **Reverb**. Рядом: MySQL, Redis, Horizon, scheduler, Socket.IO
OTP-шлюз. Наружу открыты Caddy (80/443) и порт 3000 OTP-шлюза — последний
обязательно ограничить по source IP, см. раздел ниже.

Обновление кода — `git pull` + пересборка образа на сервере.

---

## 1. Подготовка сервера

Ubuntu 22.04/24.04, минимум 2 vCPU / 4 ГБ RAM / 40 ГБ диска (видео занимают
много — под `/var/lib/docker` желательно отдельный том).

```bash
# Docker Engine + compose plugin
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER   # перелогиниться после этого

# Firewall: наружу только SSH и HTTP(S)
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Порт OTP-шлюза — ТОЛЬКО с IP телефона-шлюза, не для всех.
# Почему это критично — см. раздел «OTP-шлюз на порту 3000» ниже.
sudo ufw allow from <IP_ТЕЛЕФОНА_ШЛЮЗА> to any port 3000 proto tcp

sudo ufw enable
```

DNS: A-запись `dashoguzsowda.com.tm` → IP сервера. Домен обслуживает только
админку и мобильное API; OTP-шлюз работает напрямую по порту 3000.

## 2. Код и конфигурация

```bash
sudo mkdir -p /srv/dzsowda && sudo chown $USER:$USER /srv/dzsowda
git clone <repo-url> /srv/dzsowda
cd /srv/dzsowda

cp .env.production.example .env
```

Заполнить `.env` — все поля с пустым значением обязательны:

```bash
openssl rand -base64 24   # DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD
openssl rand -hex 16      # REVERB_APP_KEY, REVERB_APP_SECRET
openssl rand -hex 24      # OTP_SECRET (тот же прописать в телефоне-шлюзе)
```

`APP_KEY` генерируется после первой сборки образа (шаг 3).

### SSL-сертификаты

Используются собственные сертификаты (не Let's Encrypt). Положить в
`docker/caddy/certs/` — каталог в git не коммитится:

```
docker/caddy/certs/fullchain.pem   сертификат домена + промежуточные CA
docker/caddy/certs/privkey.pem     приватный ключ без пароля
```

```bash
chmod 644 docker/caddy/certs/fullchain.pem
chmod 600 docker/caddy/certs/privkey.pem
```

Если CA выдал файлы по отдельности или ключ зашифрован — команды для сборки
цепочки и конвертации в [docker/caddy/certs/README.md](../docker/caddy/certs/README.md).

Переключиться на автоматический Let's Encrypt можно позже: убрать строки `tls`
из [docker/caddy/Caddyfile](../docker/caddy/Caddyfile) и задать `LETSENCRYPT_EMAIL`.

### Ключ Firebase для push-уведомлений

```bash
mkdir -p storage/app/firebase
# скопировать service-account.json в storage/app/firebase/
chmod 600 storage/app/firebase/service-account.json
```

## 3. Сборка и первый запуск

### Требования к сети на время сборки

Образ собирается из исходников, поэтому сборочной машине нужен доступ к:

| Хост | Зачем |
|---|---|
| `registry-1.docker.io` | базовые образы php / node / nginx / caddy / mysql / redis |
| `dl-cdn.alpinelinux.org` | системные пакеты, включая **ffmpeg** |
| `pecl.php.net` | расширение redis |
| `repo.packagist.org`, `github.com`, `codeload.github.com` | composer-зависимости |
| `registry.npmjs.org` | npm-зависимости для сборки Vite |

Проверить одной командой:

```bash
for u in https://registry-1.docker.io/v2/ https://dl-cdn.alpinelinux.org/alpine/v3.22/main/x86_64/APKINDEX.tar.gz \
         https://pecl.php.net https://repo.packagist.org/packages.json https://registry.npmjs.org/vue https://github.com; do
  printf "%s  %s\n" "$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$u")" "$u"
done
```

`000` означает, что хост недоступен. Для каждого источника есть зеркало —
задаётся в `.env`, правок в коде не требует:

| Заблокирован | Переменная в `.env` | Проверенное значение |
|---|---|---|
| `dl-cdn.alpinelinux.org` | `ALPINE_MIRROR` | `https://mirror.yandex.ru/mirrors/alpine` |
| `repo.packagist.org` | `COMPOSER_MIRROR` | `https://nexus.telecom.tm/repository/composer-proxy/` |
| `registry.npmjs.org` | `NPM_REGISTRY` | `https://nexus.telecom.tm/repository/npm-proxy/` |

`pecl.php.net` отдельной переменной не требует: расширение redis берётся по
цепочке источников — GitHub, затем pecl, затем готовый пакет `phpXX-pecl-redis`
из репозитория Alpine. Последний вариант срабатывает не всегда: нужный пакет
есть только если в этом релизе Alpine присутствует та же версия PHP, что и в
базовом образе. Надёжнее обеспечить доступ к GitHub (см. ниже про `/etc/hosts`).
Результат в любом случае проверяется через `php -m` прямо в сборке.

### Если хосты перенаправлены через /etc/hosts

Контейнеры сборки **не наследуют** `/etc/hosts` сервера. Если какой-то домен
на сервере перенаправлен на рабочий IP, то же сопоставление нужно продублировать
в `docker-compose.prod.yml` — иначе внутри сборки имя резолвится в реальный
адрес и соединение не проходит:

```yaml
  app:
    build:
      target: prod
      extra_hosts:
        - "github.com:4.237.22.38"
```

Проверить, как имя резолвится именно из контейнера:

```bash
docker run --rm alpine sh -c 'getent hosts github.com'
```

Проверить зеркало перед сборкой:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://mirror.yandex.ru/mirrors/alpine/v3.22/main/x86_64/APKINDEX.tar.gz
curl -s -o /dev/null -w "%{http_code}\n" https://nexus.telecom.tm/repository/composer-proxy/packages.json
curl -s -o /dev/null -w "%{http_code}\n" https://nexus.telecom.tm/repository/npm-proxy/vue
```

### Запасной путь для ассетов

Если ни один npm-реестр не открывается, `public/build` можно собрать заранее на
машине, где уже есть `node_modules`, и закоммитить — Dockerfile увидит готовый
`public/build/manifest.json` и пропустит npm:

```bash
# на машине с node_modules, с прод-значениями VITE_*
VITE_REVERB_APP_KEY=<ключ> VITE_REVERB_HOST=dashoguzsowda.com.tm \
VITE_REVERB_PORT=443 VITE_REVERB_SCHEME=https npm run build

git add -f public/build && git commit -m "chore: prebuilt assets" && git push
```

Важно: значения `VITE_*` вкомпилируются в бандл на этапе сборки, поэтому
собирать нужно именно с прод-значениями, а не с localhost.

Если совсем ничего не открывается — собрать образ целиком там, где доступ есть,
и перенести:

```bash
# на машине со сборкой
docker save dzsowda-app:latest dzsowda-web:latest dzsowda-socket:latest | gzip > images.tar.gz
# на сервере
gunzip -c images.tar.gz | docker load
docker compose -f docker-compose.prod.yml up -d   # без --build
```

### Сборка

```bash
docker compose -f docker-compose.prod.yml build

# APP_KEY (записать результат в .env)
docker run --rm dzsowda-app:latest php artisan key:generate --show

docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml logs -f app
```

Entrypoint `app` сам дожидается MySQL, накатывает миграции (`migrate --force`),
создаёт симлинк `public/storage` и прогревает `config/route/view/event` кэши.
Остальные PHP-контейнеры миграции не запускают.

Начальные данные (роли, регионы, категории, дефолтный тариф):

```bash
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```

Проверка:

```bash
curl -I https://dashoguzsowda.com.tm            # 200, сертификат валиден
curl https://dashoguzsowda.com.tm/up            # healthcheck Laravel
docker compose -f docker-compose.prod.yml ps   # все healthy
```

## 4. Обновление версии

```bash
cd /srv/dzsowda
git pull

docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d

# опционально: убрать старые образы
docker image prune -f
```

Миграции применяются автоматически при старте `app`. Короткий простой во время
рестарта есть — zero-downtime потребует второй реплики и отдельного шага.

Если менялся `.env` (кроме `VITE_*`) — достаточно рестарта, кэши пересобираются
в entrypoint:

```bash
docker compose -f docker-compose.prod.yml restart app horizon scheduler reverb
```

Если менялись `REVERB_APP_KEY` или `APP_DOMAIN` — **нужна пересборка**: эти
значения вкомпилированы в JS-бандл на этапе сборки образа.

## 5. Эксплуатация

```bash
CO="docker compose -f docker-compose.prod.yml"

$CO logs -f app horizon          # логи
$CO exec app php artisan queue:failed
$CO exec app php artisan queue:retry all
$CO exec app php artisan horizon:status
$CO exec app php artisan tinker
```

Horizon-дашборд доступен на `https://dashoguzsowda.com.tm/horizon` (только для admin).

### Бэкапы

Что нужно бэкапить: БД, том с загрузками, `.env`, ключ Firebase.

```bash
# База
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysqldump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction --routines \
  "$DB_DATABASE" | gzip > /srv/backups/db-$(date +%F).sql.gz

# Загрузки пользователей (named-том app_storage)
docker run --rm -v dzsowda-prod_app_storage:/data -v /srv/backups:/backup alpine \
  tar czf /backup/storage-$(date +%F).tar.gz -C /data .
```

Имя проекта в прод-компоузе — `dzsowda-prod`, отсюда префикс у томов
(`docker volume ls` для проверки). Dev- и прод-стеки из-за этого не пересекаются
по данным, но `container_name` у них совпадают — на одной машине одновременно
их не поднять.

### Продление сертификатов

Сертификаты свои, поэтому продление ручное. При замене файлов в
`docker/caddy/certs/` достаточно перезагрузить Caddy без простоя:

```bash
docker compose -f docker-compose.prod.yml exec caddy \
  caddy reload --config /etc/caddy/Caddyfile
```

Срок действия текущего сертификата:

```bash
openssl x509 -enddate -noout -in docker/caddy/certs/fullchain.pem
```

### OTP-шлюз на порту 3000

Телефон Flutter SMS-шлюза подключается напрямую на `3000`. У этого порта есть
особенность, которую нужно учитывать при настройке firewall.

`socket-server/index.js` не проверяет подключающихся клиентов: `io.on('connection')`
принимает любого, `cors.origin` — `*`, а рассылка идёт через `io.emit(...)`, то
есть **каждый подключённый сокет получает OTP-коды всех пользователей**. Секрет
`GATEWAY_SECRET` защищает только входящий `POST /emit-otp` от Laravel, но не
подключение по socket.io.

Практический вывод: любой, кто дотянется до порта 3000, сможет читать коды
подтверждения и входить в чужие аккаунты. Поэтому порт открывается только для
IP телефона-шлюза:

```bash
sudo ufw allow from <IP_ТЕЛЕФОНА_ШЛЮЗА> to any port 3000 proto tcp
sudo ufw status numbered      # убедиться, что нет правила "3000 ALLOW Anywhere"
```

Проверить, что снаружи порт закрыт (с любой другой машины):

```bash
nc -zv -w5 <IP_СЕРВЕРА> 3000    # должно быть timeout / refused
```

Если у телефона динамический IP (мобильный оператор), фиксированное правило не
сработает. Тогда варианты: держать телефон на статическом IP или в VPN до
сервера, либо добавить в `socket-server` проверку секрета при подключении
(`io.use(...)` с токеном в `socket.handshake.auth`) — сейчас её нет.

Трафик идёт по HTTP без TLS, то есть коды передаются в открытом виде. В пределах
доверенной сети это приемлемо, через интернет — нет.

### Доступ к БД

Порт 3306 наружу не публикуется. Для phpMyAdmin/DBeaver — SSH-туннель:

```bash
ssh -L 3306:127.0.0.1:3306 user@server \
  docker compose -f /srv/dzsowda/docker-compose.prod.yml exec mysql true
```

Проще — временно поднять phpMyAdmin из dev-компоуза или работать через
`exec mysql mysql -u...`.

---

## Что осталось сделать вручную перед публичным запуском

1. **Прописать `OTP_SECRET` в телефоне-отправителе SMS.** Одно и то же значение
   в `.env` сервера и в настройках приложения на телефоне. Телефон подключается
   к `http://<IP_СЕРВЕРА>:3000`, передавая секрет в `auth: {'secret': ...}` —
   контракт и пример на Flutter в
   [socket-server/README.md](../socket-server/README.md).
2. **Ограничить порт 3000 в ufw** по IP телефона — см. раздел
   «OTP-шлюз на порту 3000».
3. **Проверить отправку кода** на реальном номере: `SMS_DRIVER=modem` в `.env`,
   телефон подключён (`/health` показывает `clients: 1`), запросить код через
   мобильное приложение.
4. **Проверить FCM** на реальном устройстве после деплоя (ключ, `FIREBASE_PROJECT_ID`).
5. **Настроить мониторинг диска** — видео и WebP-варианты растут быстро.
