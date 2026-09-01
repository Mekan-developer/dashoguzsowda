FROM node:22 AS sms-gateway
WORKDIR /var/www/socket-server

# 1. Подключаем внутренний прокси Nexus для npm
ARG NPM_REGISTRY=https://nexus.telecom.tm/repository/npm-proxy/
RUN if [ -n "$NPM_REGISTRY" ]; then npm config set registry "$NPM_REGISTRY"; fi

COPY package*.json ./
# 3. Устанавливаем только продакшен-зависимости с очисткой npm-кэша
RUN npm ci --omit=dev && npm cache clean --force
COPY . .

# 4. Запускаем от непривилегированного пользователя node для безопасности
USER node

CMD ["node", "index.js"]
