# Деплой на Ubuntu-сервер

Схема: **host nginx** (80/443, TLS-терминация, свои сертификаты) → **nginx в
докере** (`127.0.0.1:8000`, статика + маршрутизация) → **php-fpm** / **Reverb**.
Рядом: MySQL, Redis, Horizon, scheduler, Socket.IO OTP-шлюз. Наружу открыты
host nginx (80/443) и порт 3000 OTP-шлюза — последний обязательно ограничить
по source IP, см. раздел ниже. Host nginx — системный пакет на сервере, живёт
вне этого репозитория и вне docker-стека, настраивается один раз при подготовке
сервера (раздел 1).

Обновление кода — `git pull` + пересборка образа на сервере.

---

## 1. Подготовка сервера

Ubuntu 22.04/24.04, минимум 2 vCPU / 4 ГБ RAM / 40 ГБ диска (видео занимают
много — под `/var/lib/docker` желательно отдельный том).

```bash
# Docker Engine + compose plugin
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER   # перелогиниться после этого

# Host nginx — TLS-терминация перед docker-стеком, настройка в разделе 2
sudo apt update && sudo apt install -y nginx

# Firewall: наружу только SSH и HTTP(S)
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Порт OTP-шлюза — ТОЛЬКО с IP телефона-шлюза, не для всех.
# Почему это критично — см. раздел «OTP-шлюз на порту 3000» ниже.
sudo ufw allow from <IP_ТЕЛЕФОНА_ШЛЮЗА> to any port 3000 proto tcp

sudo ufw enable
```

Порту 8000 (контейнерный nginx, `docker-compose.yml`) отдельное правило ufw не
нужно — он слушает только `127.0.0.1`, наружу не торчит, ходит в него только
host nginx с той же машины.

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

Используются собственные сертификаты (не Let's Encrypt), их читает host
nginx напрямую с диска сервера — например, из `/etc/nginx/ssl/<домен>/`:

```bash
sudo mkdir -p /etc/nginx/ssl/dashoguzsowda.com.tm
sudo cp fullchain.pem privkey.pem /etc/nginx/ssl/dashoguzsowda.com.tm/
sudo chmod 644 /etc/nginx/ssl/dashoguzsowda.com.tm/fullchain.pem
sudo chmod 600 /etc/nginx/ssl/dashoguzsowda.com.tm/privkey.pem
```

`fullchain.pem` — сертификат домена + промежуточные сертификаты CA в одном
файле. Если CA выдал файлы по отдельности или ключ зашифрован — собрать цепочку
и расшифровать ключ стандартными командами `cat`/`openssl rsa -in ... -out ...`.

Переключение на автоматический выпуск (certbot) — отдельная настройка host
nginx, вне этого репозитория.

### Конфиг host nginx

`/etc/nginx/sites-available/dashoguzsowda.com.tm`:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 80;
    server_name dashoguzsowda.com.tm;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name dashoguzsowda.com.tm;

    ssl_certificate     /etc/nginx/ssl/dashoguzsowda.com.tm/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/dashoguzsowda.com.tm/privkey.pem;

    # Совпадает с лимитом в docker/nginx/conf.d/nginx.conf — если поднимать
    # один, поднимать и второй, иначе host nginx обрежет запрос раньше, чем
    # он дойдёт до контейнера.
    client_max_body_size 150M;
    proxy_read_timeout 300s;
    proxy_send_timeout 300s;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    }

    # Опционально: HTTPS-доступ к тестовой странице OTP-шлюза, см. раздел
    # «OTP-шлюз на порту 3000». Нужно, только пока SMS_GATEWAY_TEST_PAGE=true —
    # можно не добавлять эти два location сразу, а вписать при отладке.
    location = /test-otp.html {
        proxy_pass http://127.0.0.1:3000/test;
    }

    location /otp/ {
        rewrite ^/otp/(.*)$ /$1 break;
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $host;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/dashoguzsowda.com.tm /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

`bootstrap/app.php` уже вызывает `trustProxies(at: '*')`, поэтому Laravel
корректно увидит `X-Forwarded-Proto` от host nginx и `SESSION_SECURE_COOKIE`
отработает как надо — на стороне приложения ничего донастраивать не нужно.

### Ключ Firebase для push-уведомлений

```bash
mkdir -p storage/app/firebase
# скопировать service-account.json в storage/app/firebase/
chmod 600 storage/app/firebase/service-account.json
```

## 3. Сборка и первый запуск

```bash
CO="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
```

`docker-compose.prod.yml` — это оверрайд (без db/redis/build для app), сам по
себе не разворачивается: нужны оба файла, `-f docker-compose.prod.yml` в
одиночку упадёт с ошибкой «no image specified». Дальше в этом документе `$CO`
подразумевает оба флага — переобъявить в каждой новой SSH-сессии.

### Требования к сети на время сборки

php-стейдж образа собирается на `php:8.3-fpm` (Debian trixie), системные
пакеты (включая **ffmpeg**) ставятся через apt, а не apk. Сборочной машине
нужен доступ к:

| Хост | Зачем |
|---|---|
| `registry-1.docker.io` | базовые образы php / node / nginx / mysql / redis |
| apt-зеркало (`APT_MIRROR`, по умолчанию `nexus.telecom.tm/repository/debian-proxy`) | системные пакеты Debian, включая ffmpeg |
| `repo.packagist.org`, `github.com`, `codeload.github.com` | composer-зависимости |
| `registry.npmjs.org` | npm-зависимости для сборки Vite |

По умолчанию `APT_MIRROR` в `docker/php/Dockerfile` уже указывает на
внутреннее зеркало — если сервер и так в закрытой сети, публичный
`deb.debian.org` не требуется. Переопределяется build-arg `APT_MIRROR`.

⚠ **Известная нестыковка:** build-arg в `docker-compose.yml` называется
`DEBIAN_MIRROR`, а `Dockerfile` читает `APT_MIRROR` — значение из `.env` до
сборки не долетает из-за разных имён. Пока имена не приведены к одному —
переопределять зеркало через `.env` бесполезно, нужно передавать явно:
`$CO build --build-arg APT_MIRROR=...`.

Проверить одной командой:

```bash
for u in https://registry-1.docker.io/v2/ https://nexus.telecom.tm/repository/debian-proxy/dists/trixie/Release \
         https://repo.packagist.org/packages.json https://registry.npmjs.org/vue https://github.com; do
  printf "%s  %s\n" "$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$u")" "$u"
done
```

`000` означает, что хост недоступен. Для composer/npm есть зеркала —
задаются в `.env`, правок в коде не требуют:

| Заблокирован | Переменная в `.env` | Проверенное значение |
|---|---|---|
| `repo.packagist.org` | `COMPOSER_MIRROR` | `https://nexus.telecom.tm/repository/composer-proxy/` |
| `registry.npmjs.org` | `NPM_REGISTRY` | `https://nexus.telecom.tm/repository/npm-proxy/` |

### Если хосты перенаправлены через /etc/hosts

Контейнеры сборки **не наследуют** `/etc/hosts` сервера. Если какой-то домен
на сервере перенаправлен на рабочий IP, то же сопоставление нужно продублировать
в `docker-compose.prod.yml` — иначе внутри сборки имя резолвится в реальный
адрес и соединение не проходит:

```yaml
  app:
    build:
      extra_hosts:
        - "github.com:4.237.22.38"
```

Проверить, как имя резолвится именно из контейнера:

```bash
docker run --rm alpine sh -c 'getent hosts github.com'
```

Проверить зеркало перед сборкой:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://nexus.telecom.tm/repository/debian-proxy/dists/trixie/Release
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
docker save dzsowda-php:local dzsowda-nginx:local dzsowda-sms:local | gzip > images.tar.gz
# на сервере
gunzip -c images.tar.gz | docker load
$CO up -d   # без --build
```

### Сборка

```bash
$CO build

# APP_KEY (записать результат в .env)
docker run --rm dzsowda-php:local php artisan key:generate --show

$CO up -d
$CO logs -f app
```

Entrypoint `app` сам дожидается MySQL, накатывает миграции (`migrate --force`),
создаёт симлинк `public/storage` и прогревает `config/route/view/event` кэши.
Остальные PHP-контейнеры миграции не запускают.

Начальные данные (роли, регионы, категории, дефолтный тариф):

```bash
$CO exec app php artisan db:seed --force
```

Проверка:

```bash
curl -I https://dashoguzsowda.com.tm            # 200, сертификат валиден
curl https://dashoguzsowda.com.tm/up            # healthcheck Laravel
$CO ps                                          # все healthy
sudo systemctl status nginx                     # host nginx поднят
```

## 4. Обновление версии

```bash
cd /srv/dzsowda
git pull

CO="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
$CO build
$CO up -d

# опционально: убрать старые образы
docker image prune -f
```

Миграции применяются автоматически при старте `app`. Короткий простой во время
рестарта есть — zero-downtime потребует второй реплики и отдельного шага.

Если менялся `.env` (кроме `VITE_*`) — достаточно рестарта, кэши пересобираются
в entrypoint:

```bash
$CO restart app horizon scheduler reverb
```

Если менялись `REVERB_APP_KEY` или `APP_DOMAIN` — **нужна пересборка**: эти
значения вкомпилированы в JS-бандл на этапе сборки образа.

## 5. Эксплуатация

```bash
CO="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

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
# База (сервис называется db, не mysql)
$CO exec -T db \
  mysqldump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction --routines \
  "$DB_DATABASE" | gzip > /srv/backups/db-$(date +%F).sql.gz

# Загрузки пользователей — точное имя тома узнать через:
docker volume ls | grep storage_app

docker run --rm -v <имя_тома_из_команды_выше>:/data -v /srv/backups:/backup alpine \
  tar czf /backup/storage-$(date +%F).tar.gz -C /data .
```

Имя проекта в compose берётся из `COMPOSE_PROJECT_NAME` в `.env` (по
умолчанию, если не задано — из имени папки, `/srv/dzsowda` → `dzsowda`),
отсюда префикс у томов — не считать его равным `dzsowda-prod`, а проверять
через `docker volume ls`.

### Продление сертификатов

Сертификаты свои, продление ручное. После замены файлов в
`/etc/nginx/ssl/dashoguzsowda.com.tm/` достаточно перезагрузить host nginx без
простоя:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Срок действия текущего сертификата:

```bash
openssl x509 -enddate -noout -in /etc/nginx/ssl/dashoguzsowda.com.tm/fullchain.pem
```

### OTP-шлюз на порту 3000

Телефон Flutter SMS-шлюза подключается напрямую на `3000`. У этого порта есть
особенность, которую нужно учитывать при настройке firewall.

`socket-server/index.js` проверяет секрет при подключении (`io.use(...)`):
клиент обязан передать `OTP_SECRET` в `socket.handshake.auth.secret` (либо
`?secret=`, либо заголовком `X-Otp-Secret`), иначе handshake отклоняется с
ошибкой `unauthorized`. Тот же секрет требуется на `POST /emit-otp` в заголовке
`X-Otp-Secret`.

Это единственная защита: рассылка идёт через `io.emit(...)`, то есть **любой
прошедший проверку сокет получает OTP-коды всех пользователей**, а `cors.origin`
— `*`. Секрет утёк → чужие коды читаются. Поэтому порт дополнительно
открывается только для IP телефона-шлюза:

```bash
sudo ufw allow from <IP_ТЕЛЕФОНА_ШЛЮЗА> to any port 3000 proto tcp
sudo ufw status numbered      # убедиться, что нет правила "3000 ALLOW Anywhere"
```

Проверить, что снаружи порт закрыт (с любой другой машины):

```bash
nc -zv -w5 <IP_СЕРВЕРА> 3000    # должно быть timeout / refused
```

Если у телефона динамический IP (мобильный оператор), фиксированное правило не
сработает: телефон перестанет подключаться после смены адреса. Тогда варианты —
держать телефон на статическом IP или в VPN до сервера, либо (менее безопасно)
открыть порт всем и полагаться только на `OTP_SECRET`.

Диагностика, когда телефон «не подключается»:

```bash
# Логи шлюза: видно и успешные подключения, и отказ по секрету.
# Сервис в compose называется sms-gateway (container_name: dzsowda_socket).
$CO logs -f sms-gateway
#   [gateway] client connected: <id> (total: 1)          — телефон на связи
#   [gateway] отклонено подключение с неверным секретом: <ip>  — секрет не совпал

# Сколько телефонов подключено сейчас (то же показывает админка → Настройки → SMS-шлюз):
curl -s http://127.0.0.1:3000/health     # {"status":"ok","clients":1}

# Порт виден с самого телефона / из его сети?
nc -zv -w5 <IP_СЕРВЕРА> 3000
```

`clients: 0` при рабочем `/health` = сеть в порядке, проблема в секрете или в
том, что телефон вообще не подключился. Обрыв на уровне TCP = firewall/ufw.

Если этого мало — есть страница-тестер, которая симулирует телефон в браузере
(подключение с секретом + приём OTP-событий + прямой вызов `/emit-otp`):

```bash
# в .env сервера
SMS_GATEWAY_TEST_PAGE=true
$CO up -d sms-gateway
```

Открывать одним из двух способов:

| Адрес | Когда |
|---|---|
| `https://<домен>/test-otp.html` | из интернета, по TLS — работает откуда угодно |
| `http://<IP_СЕРВЕРА>:3000/test` | из LAN телефона или через `ssh -L 3000:127.0.0.1:3000` |

HTTPS-вариант проксирует host nginx (см. `location /otp/` и
`location = /test-otp.html` в конфиге из раздела 2): `/test-otp.html` →
страница, `/otp/*` → сам шлюз (`/otp/socket.io/*` и `/otp/emit-otp`, префикс
`/otp` перед проксированием обрезается). Префикс нужен потому, что браузер
разрешает странице по https обращаться только к своему origin: `ws://` на порт
3000 он заблокирует как mixed content, а кросс-origin `POST` — по CORS.

После отладки вернуть `SMS_GATEWAY_TEST_PAGE=false` и перезапустить
`sms-gateway`. Пока флаг включён, `/otp/socket.io` и `/otp/emit-otp` доступны
из интернета — защищает их только `OTP_SECRET`, и любой прошедший проверку
сокет получает OTP-коды всех пользователей.

Трафик от host nginx до шлюза (127.0.0.1:3000) и от телефона до сервера на
порт 3000 напрямую идёт по HTTP без TLS, то есть коды передаются в открытом
виде на этом отрезке. В пределах доверенной сети это приемлемо, через
интернет — нет.

### Доступ к БД

Порт 3306 наружу не публикуется. Для phpMyAdmin/DBeaver — SSH-туннель:

```bash
ssh -L 3306:127.0.0.1:3306 user@server \
  docker compose -f /srv/dzsowda/docker-compose.yml -f /srv/dzsowda/docker-compose.prod.yml exec db true
```

Проще — временно поднять phpMyAdmin из dev-компоуза или работать через
`exec db mysql -u...`.

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
