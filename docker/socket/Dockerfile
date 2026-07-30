# Контекст сборки — ./socket-server
FROM node:20-alpine

ENV NODE_ENV=production

WORKDIR /app

# Зеркало/прокси npm-реестра. Пусто — официальный registry.npmjs.org.
# То же значение, что NPM_REGISTRY для образа PHP (см. docker/php/Dockerfile).
ARG NPM_REGISTRY=""

# npm ci на этапе сборки, а не при каждом старте контейнера.
COPY package.json package-lock.json ./
RUN if [ -n "${NPM_REGISTRY}" ]; then npm config set registry "${NPM_REGISTRY}"; fi \
 && npm ci --omit=dev --no-audit --no-fund

COPY . .

# Проверка, что зависимости реально на месте: иначе контейнер падал бы уже
# в рантайме с MODULE_NOT_FOUND, а сборка при этом считалась успешной.
RUN node -e "require('dotenv'); require('express'); require('socket.io'); console.log('[socket] зависимости на месте')"

USER node

EXPOSE 3000
CMD ["node", "index.js"]
