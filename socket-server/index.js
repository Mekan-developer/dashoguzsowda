require('dotenv').config();

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const PORT = process.env.PORT || 3000;
// Общий секрет: тот же, что в OTP_SECRET на сервере Laravel и в настройках
// телефона-отправителя SMS.
const OTP_SECRET = process.env.OTP_SECRET || '';
const OTP_EVENT_NAME = process.env.OTP_EVENT_NAME || 'otp';

if (!OTP_SECRET) {
  console.error('[gateway] FATAL: не задан OTP_SECRET — запуск без секрета запрещён');
  process.exit(1);
}

const app = express();
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, { cors: { origin: '*' } });

// Подключение по socket.io требует секрета: без этой проверки любой, кто
// дотянулся до порта, получал бы OTP всех пользователей через io.emit.
io.use((socket, next) => {
  const provided =
    socket.handshake.auth?.secret ||
    socket.handshake.query?.secret ||
    (socket.handshake.headers?.['x-otp-secret'] ?? '');

  if (provided !== OTP_SECRET) {
    const ip = socket.handshake.address;
    console.warn(`[gateway] отклонено подключение с неверным секретом: ${ip}`);

    return next(new Error('unauthorized'));
  }

  return next();
});

io.on('connection', (socket) => {
  console.log(`[gateway] client connected: ${socket.id} (total: ${io.engine.clientsCount})`);

  socket.on('disconnect', () => {
    console.log(`[gateway] client disconnected: ${socket.id} (total: ${io.engine.clientsCount})`);
  });
});

// Laravel вызывает этот эндпоинт при запросе OTP (LocalModemSmsService).
// Он ре-эмитит payload как socket.io-событие, которое слушает телефон.
app.post('/emit-otp', (req, res) => {
  if (req.get('X-Otp-Secret') !== OTP_SECRET) {
    return res.status(401).json({ message: 'Unauthorized' });
  }

  const { phone_number: phoneNumber, otp } = req.body || {};

  if (!phoneNumber || !otp) {
    return res.status(422).json({ message: 'phone_number and otp are required' });
  }

  if (io.engine.clientsCount === 0) {
    console.warn('[gateway] no SMS-gateway phone connected, OTP event dropped');

    return res.status(503).json({ message: 'No gateway client connected' });
  }

  io.emit(OTP_EVENT_NAME, { phone_number: phoneNumber, otp });
  console.log(`[gateway] OTP emitted for ${phoneNumber}`);

  return res.json({ message: 'OTP event emitted' });
});

// Используется мониторингом в админке (Settings → SMS-шлюз). Секрета не
// требует: отдаёт только факт доступности и число подключённых телефонов.
app.get('/health', (req, res) => {
  res.json({ status: 'ok', clients: io.engine.clientsCount });
});

server.listen(PORT, () => {
  console.log(`[gateway] socket.io server listening on :${PORT}, event="${OTP_EVENT_NAME}"`);
});
