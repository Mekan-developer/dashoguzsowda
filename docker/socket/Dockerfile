# Контекст сборки — ./socket-server
FROM node:20-alpine

ENV NODE_ENV=production

WORKDIR /app

# npm ci на этапе сборки, а не при каждом старте контейнера.
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund

COPY . .

USER node

EXPOSE 3000
CMD ["node", "index.js"]
