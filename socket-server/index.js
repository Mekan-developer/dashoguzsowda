require('dotenv').config();

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const PORT = process.env.PORT || 3000;
const GATEWAY_SECRET = process.env.GATEWAY_SECRET || '';
const OTP_EVENT_NAME = process.env.OTP_EVENT_NAME || 'otp';

const app = express();
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, { cors: { origin: '*' } });

io.on('connection', (socket) => {
  console.log(`[gateway] client connected: ${socket.id} (total: ${io.engine.clientsCount})`);

  socket.on('disconnect', () => {
    console.log(`[gateway] client disconnected: ${socket.id} (total: ${io.engine.clientsCount})`);
  });
});

// Laravel вызывает этот эндпоинт при запросе OTP. Он ре-эмитит payload как
// Socket.IO событие, которое слушает телефон Flutter SMS-шлюза.
app.post('/emit-otp', (req, res) => {
  if (GATEWAY_SECRET && req.get('X-Gateway-Secret') !== GATEWAY_SECRET) {
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

app.get('/health', (req, res) => {
  res.json({ status: 'ok', clients: io.engine.clientsCount });
});

server.listen(PORT, () => {
  console.log(`[gateway] socket.io server listening on :${PORT}, event="${OTP_EVENT_NAME}"`);
});
