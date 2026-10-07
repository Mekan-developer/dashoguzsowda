# Деплой на сервер

## Как устроен прод

```
интернет ──443──▶ nginx на сервере (TLS, свои сертификаты)
                    │  docker/nginx/host/dashoguzsowda.com.tm.conf
                    ▼
                 127.0.0.1:8000 ── nginx в контейнере (public/, статика)
                                     ├─▶ app:9000      php-fpm (админка, API)
                                     └─▶ reverb:8080   WebSocket (/app)
телефон-шлюз ──3000──▶ sms-gateway (OTP)

рядом:  horizon (очереди)  scheduler (cron)  db (MySQL 8)  redis
```

- **Код живёт в образах**, на сервере исходники нужны только для сборки.
  В PHP-образ попадает белый список каталогов (`docker/php/Dockerfile`):
  `app bootstrap config database lang public resources/views routes vendor`.
- **Наружу** открыты только 80/443 (nginx на сервере) и 3000 (OTP-шлюз).
  Контейнерный nginx слушает `127.0.0.1:8000`, MySQL и Redis не публикуются.
- **Окружение** — `.env.production` на сервере. Контейнеры получают его как
  переменные (`env_file`), файлом в контейнер он не попадает.
- **Данные** — в именованных томах:

  | Том | Что |
  |---|---|
  | `dashoguzsowda_db_data` | MySQL |
  | `dashoguzsowda_redis_data` | Redis (очереди, кэш, сессии, OTP) |
  | `dashoguzsowda_storage_app` | загрузки: фото, видео, аватары |
  | `dashoguzsowda_storage_logs` | логи Laravel |

  Префикс — имя проекта, закреплённое в `docker-compose.yml` (`name:`).
  **Не задавать `COMPOSE_PROJECT_NAME`** в `.env.production`: с другим
  именем compose создаст новые пустые тома, и сайт поднимется с пустой базой.
- **Миграции** применяет контейнер `app` при каждом старте
  (`RUN_MIGRATIONS=true`), остальные PHP-контейнеры их не трогают.

## Сокращение команд

Все команды ниже — через алиас:

```bash
echo "alias dc='docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.prod.yml'" >> ~/.bashrc
source ~/.bashrc
```

| Часть | Зачем |
|---|---|
| `--env-file .env.production` | Из него compose подставляет пароли БД/Redis, `OTP_SECRET` и `VITE_*` в сервисы и сборку. Без флага возьмётся `.env`, которого на сервере нет |
| `-f docker-compose.prod.yml` | Прод-оверрайд: тома, порт только на localhost, пароль Redis, ротация логов. Без него поднимется дев-конфигурация |

Команды выполняются из `/srv/dashoguzsowda`.

## Зеркала

С сервера закрыта часть внешних хостов, поэтому всё ставится через зеркала:

| Что | Откуда | Где задаётся |
|---|---|---|
| Базовые образы (php, node, nginx, mysql, redis) | Nexus `docker-proxy` | `registry-mirrors` в `/etc/docker/daemon.json` |
| apt внутри PHP-образа | Nexus `debian-proxy` | `APT_MIRROR` (запасной — `https://mirror.yandex.ru/debian`, в разы медленнее) |
| composer | Nexus `composer-proxy` | `COMPOSER_MIRROR` |
| npm | Nexus `npm-proxy` | `NPM_REGISTRY` |
| apt на самом сервере (Ubuntu) | `mirror.yandex.ru/ubuntu` | `/etc/apt/sources.list*` |
| Docker Engine | пакеты — `mirror.yandex.ru/mirrors/docker`, ключ — `download.docker.com` | см. ниже |

Проверить доступность с сервера:

```bash
for u in https://nexus.telecom.tm/repository/debian-proxy/dists/trixie/Release \
         https://nexus.telecom.tm/repository/composer-proxy/packages.json \
         https://nexus.telecom.tm/repository/npm-proxy/vue \
         https://nexus.telecom.tm/repository/docker-proxy/v2/; do
  printf "%s  %s\n" "$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$u")" "$u"
done
```

`000` — хост недоступен. Для `docker-proxy` нормален `401`/`200`.
Проверять без VPN: через VPN Nexus не отвечает.

**Часы сервера должны идти верно.** Если время отстаёт, любое HTTPS-зеркало
отвечает `x509: certificate ... is not yet valid`, а приложение пишет неверные
даты в заказы и сроки тарифов:

```bash
timedatectl                      # System clock synchronized: yes
sudo timedatectl set-ntp true
```

Если внешние NTP закрыты — прописать внутренний (`NTP=<адрес>` в
`/etc/systemd/timesyncd.conf`, затем `sudo systemctl restart systemd-timesyncd`).

---

## Первая установка

### 1. Сервер

Ubuntu 22.04/24.04, от 2 vCPU / 4 ГБ RAM / 40 ГБ диска (видео растут быстро —
под `/var/lib/docker` лучше отдельный том).

```bash
# Ubuntu-пакеты — с зеркала Яндекса
sudo sed -i 's|http://\(archive\|security\).ubuntu.com|https://mirror.yandex.ru|g' \
    /etc/apt/sources.list /etc/apt/sources.list.d/*.sources 2>/dev/null
sudo apt update

# nginx на сервере — TLS перед docker
sudo apt install -y nginx

# Docker Engine — пакеты с зеркала Яндекса, ключ подписи — с download.docker.com
# (на Яндексе ключа нет). Если download.docker.com закрыт — пакеты Ubuntu:
#   sudo apt install -y docker.io docker-compose-v2
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://mirror.yandex.ru/mirrors/docker $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update && sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker $USER    # перелогиниться

# Базовые образы — через Nexus
echo '{ "registry-mirrors": ["https://nexus.telecom.tm/repository/docker-proxy"] }' \
  | sudo tee /etc/docker/daemon.json
sudo systemctl restart docker

# Firewall для самого сервера
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Порт 3000 (OTP) отдельно открывать не нужно и **ограничить через ufw нельзя**:
порты, опубликованные Docker, обходят ufw — см. «OTP-шлюз».

DNS: A-запись `dashoguzsowda.com.tm` → IP сервера.

### 2. Код и окружение

```bash
sudo mkdir -p /srv/dashoguzsowda && sudo chown $USER:$USER /srv/dashoguzsowda
git clone <repo-url> /srv/dashoguzsowda
cd /srv/dashoguzsowda

cp .env.production.example .env.production
nano .env.production
```

Все пустые значения обязательны:

```bash
openssl rand -base64 24   # DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD
openssl rand -hex 16      # REVERB_APP_KEY, REVERB_APP_SECRET
openssl rand -hex 24      # OTP_SECRET (тот же — в телефоне-шлюзе)
```

`UID`/`GID` — вывод `id -u` / `id -g` на сервере. `APP_KEY` — на шаге 4.

Ключ Firebase (каталог монтируется в контейнеры только для чтения):

```bash
mkdir -p storage/app/firebase
cp service-account.json storage/app/firebase/
chmod 644 storage/app/firebase/service-account.json
```

### 3. Сборка

```bash
dc build
```

Собираются три образа: `dzsowda-php` (app, horizon, reverb, scheduler),
`dzsowda-nginx`, `dzsowda-sms`. Значения `VITE_*` вкомпилируются в JS
админки — поменялись `REVERB_APP_KEY` или домен, нужна пересборка.

### 4. Ключ приложения

```bash
dc run --rm artisan key:generate --show
```

Результат (`base64:...`) — в `APP_KEY=` в `.env.production`.

### 5. Запуск

```bash
dc up -d
dc logs -f app       # ждать "ready to handle connections"
dc ps                # horizon, db, redis — healthy
```

Начальные данные (роли, регионы, категории, бесплатный тариф) — один раз:

```bash
dc exec app php artisan db:seed --force
```

### 6. nginx на сервере

Сертификаты свои (не Let's Encrypt):

```bash
sudo mkdir -p /etc/nginx/ssl/dashoguzsowda.com.tm
sudo cp fullchain.pem privkey.pem /etc/nginx/ssl/dashoguzsowda.com.tm/
sudo chmod 644 /etc/nginx/ssl/dashoguzsowda.com.tm/fullchain.pem
sudo chmod 600 /etc/nginx/ssl/dashoguzsowda.com.tm/privkey.pem
```

`fullchain.pem` — сертификат домена и промежуточные CA одним файлом, ключ —
без пароля.

```bash
sudo cp docker/nginx/host/dashoguzsowda.com.tm.conf /etc/nginx/sites-available/dashoguzsowda.com.tm
sudo ln -sf /etc/nginx/sites-available/dashoguzsowda.com.tm /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Конфиг в репозитории — источник правды: поменяли его, скопировать заново
и `reload`.

### 7. Проверка снаружи

```bash
curl -I https://dashoguzsowda.com.tm               # 200/302, сертификат валиден
curl https://dashoguzsowda.com.tm/up               # 200
curl https://dashoguzsowda.com.tm/api/v1/about     # JSON
curl -I http://dashoguzsowda.com.tm                # 301 на https
```

---

## Переход со старой схемы (Caddy)

До этой схемы TLS держал контейнер `dz_sowda_caddy` от прежнего стека
(проект `dzsowda-prod`), а окружение лежало в `.env`. Переход — один раз:

```bash
cd /srv/dashoguzsowda

# 0. Часы и бэкап базы — до всего остального
timedatectl
docker exec dzsowda_mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
  | gzip > ~/db-before-migration-$(date +%F).sql.gz

# 1. Локальные правки на сервере — посмотреть и убрать, иначе pull упрётся.
#    docker/db.env pull удалит: записать из него MYSQL_ROOT_PASSWORD — это
#    пароль root живой базы, он нужен в DB_ROOT_PASSWORD на шаге 2
git status && git diff
cat docker/db.env
git pull

# 2. Окружение: .env → .env.production, сверить с .env.production.example
cp .env .env.production
diff <(grep -o '^[A-Z_]*=' .env.production.example | sort) <(grep -o '^[A-Z_]*=' .env.production | sort)
#    В .env.production обязательно: DB_HOST=db, DB_ROOT_PASSWORD (как у
#    живой базы), VITE_REVERB_HOST=dashoguzsowda.com.tm, APP_URL с https.
#    COMPOSE_PROJECT_NAME — удалить.

# 3. Ключ Firebase — на хосте в storage/app/firebase/ (см. «Первая установка», шаг 2)

# 4. Имя проекта: тома должны называться dashoguzsowda_*
docker volume ls | grep -E 'db_data|storage_app'

# 5. Сборка и перезапуск стека (не down: сеть и тома остаются)
dc build
dc up -d --remove-orphans
dc logs -f app

# 6. nginx на сервере вместо Caddy — секунды простоя между этими командами.
#    apt сам попробует запустить nginx и не сможет (80/443 занял Caddy) —
#    это ожидаемо, пакет при этом установится
sudo apt install -y nginx
sudo mkdir -p /etc/nginx/ssl/dashoguzsowda.com.tm
sudo cp docker/caddy/certs/fullchain.pem docker/caddy/certs/privkey.pem /etc/nginx/ssl/dashoguzsowda.com.tm/
#    + конфиг, как в «Первая установка», шаг 6, но без reload
sudo nginx -t
docker stop dz_sowda_caddy && sudo systemctl restart nginx
#    Откат, если что-то не так: sudo systemctl stop nginx && docker start dz_sowda_caddy

# 7. Проверка — «Первая установка», шаг 7. Затем убрать остатки Caddy
docker rm dz_sowda_caddy
docker volume rm dzsowda-prod_caddy_data dzsowda-prod_caddy_config
docker network rm dzsowda-prod_laravel
rm -rf docker/caddy      # сертификаты уже в /etc/nginx/ssl
```

Если на шаге 4 тома называются иначе (не `dashoguzsowda_*`) — **остановиться**
и не запускать `up`: стек поднимется с пустой базой. Данные живут в старых
томах, их нужно сначала перенести.

---

## Обновление

```bash
cd /srv/dashoguzsowda
git pull
dc build
dc up -d
dc logs -f app
docker image prune -f      # старые слои
```

Миграции применяются сами при старте `app`. Простой — несколько секунд,
пока пересоздаются контейнеры.

Поменялся только `.env.production` (кроме `VITE_*`) — пересборка не нужна:

```bash
dc up -d      # пересоздаст контейнеры с новым окружением
```

## Эксплуатация

```bash
dc ps
dc logs -f app horizon                     # stdout контейнеров
dc exec app tail -f storage/logs/laravel-$(date +%F).log
dc exec app php artisan horizon:status
dc exec app php artisan queue:failed
dc exec app php artisan queue:retry all
dc run --rm artisan migrate:status
```

Horizon-дашборд: `https://dashoguzsowda.com.tm/horizon` (только admin).

Логи контейнеров ротируются (по 10 МБ × 3), логи Laravel — по дням,
14 дней (`LOG_DAILY_DAYS`).

### Бэкапы

Бэкапить: базу, том с загрузками, `.env.production`, ключ Firebase.

```bash
mkdir -p /srv/backups

dc exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
  | gzip > /srv/backups/db-$(date +%F).sql.gz

docker run --rm -v dashoguzsowda_storage_app:/data:ro -v /srv/backups:/backup alpine \
  tar czf /backup/storage-$(date +%F).tar.gz -C /data .
```

### Сертификаты

Продление ручное: заменить файлы в `/etc/nginx/ssl/dashoguzsowda.com.tm/` и

```bash
sudo nginx -t && sudo systemctl reload nginx
openssl x509 -enddate -noout -in /etc/nginx/ssl/dashoguzsowda.com.tm/fullchain.pem
```

### Доступ к БД

3306 наружу не публикуется. Консоль — `dc exec db mysql -u<DB_USERNAME> -p`,
DBeaver — через SSH-туннель на сервер и `docker exec` в контейнер.

---

## OTP-шлюз

Телефон Flutter SMS-шлюза подключается напрямую на `http://<IP_СЕРВЕРА>:3000`,
передавая `OTP_SECRET` в `auth: {'secret': ...}` — контракт в
[socket-server/README.md](../socket-server/README.md).

Секрет — единственная защита: рассылка идёт через `io.emit(...)`, то есть
**любой прошедший проверку сокет получает OTP-коды всех пользователей**.
Порт опубликован Docker, а такие порты **обходят ufw** — правило
`ufw allow from <IP> to any port 3000` ничего не ограничит. Если нужно
пускать только телефон, правило ставится в цепочку Docker:

```bash
sudo iptables -I DOCKER-USER -p tcp --dport 3000 ! -s <IP_ТЕЛЕФОНА> -j DROP
```

(не переживает перезагрузку без `iptables-persistent`). Если у телефона
динамический IP — остаётся только `OTP_SECRET`, держать его длинным.

Диагностика:

```bash
dc logs -f sms-gateway
#   [gateway] client connected: <id> (total: 1)              — телефон на связи
#   [gateway] отклонено подключение с неверным секретом: <ip> — секрет не совпал
curl -s http://127.0.0.1:3000/health        # {"status":"ok","clients":1}
```

Страница-тестер, симулирующая телефон в браузере: `SMS_GATEWAY_TEST_PAGE=true`
в `.env.production`, `dc up -d sms-gateway`, открыть
`https://dashoguzsowda.com.tm/test-otp.html` (её и `/otp/*` проксирует nginx
на сервере). После отладки вернуть `false` — пока флаг включён, слушать коды
может любой, кто знает секрет.

---

## Частые ошибки

**`x509: certificate has expired or is not yet valid` при сборке** — отстают
часы сервера, см. «Зеркала».

**`FATAL: APP_KEY не задан`** — забыт шаг 4 или `dc` без `--env-file`.

**502 Bad Gateway** — `dc ps`: не поднялся `app` (смотреть `dc logs app`)
или контейнерный nginx (`dc logs nginx`).

**Сайт открылся с пустой базой** — compose поднял стек под другим именем
проекта. `docker volume ls`: данные в томах с другим префиксом. Убрать
`COMPOSE_PROJECT_NAME` из `.env.production`, `dc down`, `dc up -d`.

**Админка без чата и колокольчика** (в консоли браузера ошибка WebSocket) —
образ собран с неверными `VITE_REVERB_*`. Поправить в `.env.production`,
`dc build && dc up -d`.

**Push не приходят** — `dc exec app ls storage/app/firebase/`: ключа нет на
хосте в `/srv/dashoguzsowda/storage/app/firebase/`.

**`npm ci` / `composer install` падают с `Connection refused`** — закрыт
реестр, проверить зеркала (раздел «Зеркала»).
