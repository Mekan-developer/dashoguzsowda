# OTP-шлюз (socket.io)

Мост между Laravel и телефоном, который физически отправляет SMS.

```
Laravel (LocalModemSmsService)  --HTTP-->  socket-server  --socket.io-->  телефон
```

Один общий секрет `OTP_SECRET` на все три стороны: `.env` сервера,
`socket-server/.env` (в Docker передаётся переменной окружения) и настройки
приложения на телефоне.

## Контракт

### Laravel → шлюз

```
POST /emit-otp
X-Otp-Secret: <OTP_SECRET>
Content-Type: application/json

{"phone_number": "+99361234567", "otp": "123456"}
```

| Код | Значение |
|---|---|
| 200 | событие отправлено телефону |
| 401 | неверный секрет |
| 422 | не переданы `phone_number` или `otp` |
| 503 | ни один телефон-шлюз не подключён |

### Телефон → шлюз

Подключение по socket.io **требует секрета**, иначе сервер разрывает
соединение с ошибкой `unauthorized`. Секрет принимается тремя способами —
подойдёт любой, какой удобнее клиенту:

```dart
// Flutter, socket_io_client
final socket = IO.io('http://<IP_СЕРВЕРА>:3000', <String, dynamic>{
  'transports': ['websocket'],
  'auth': {'secret': '<OTP_SECRET>'},
});

socket.on('otp', (data) {
  final phone = data['phone_number'] as String;
  final code  = data['otp'] as String;
  // отправить SMS средствами телефона
});
```

Альтернативы: `?secret=<OTP_SECRET>` в query или заголовок `X-Otp-Secret`.

Имя события задаётся `OTP_EVENT_NAME` (по умолчанию `otp`) и должно совпадать
с тем, что слушает телефон.

### Мониторинг

```
GET /health  →  {"status": "ok", "clients": 1}
```

Секрета не требует, отдаёт только доступность и число подключённых телефонов.
Используется в админке: Настройки → SMS-шлюз.

## Замечание по безопасности

Трафик идёт по HTTP без TLS — код подтверждения передаётся открытым текстом.
Секрет защищает от постороннего подключения, но не от перехвата трафика.
Порт 3000 следует ограничить в firewall по IP телефона (см. `docs/DEPLOY.md`).
