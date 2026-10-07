# OTP-шлюз: один index.js на express + socket.io. Полный node:22 (Debian)
# весил 1.6 GB ради него — alpine хватает.
FROM node:22-alpine

ENV NODE_ENV=production
WORKDIR /app

ARG NPM_REGISTRY=https://nexus.telecom.tm/repository/npm-proxy/
COPY package.json package-lock.json ./
RUN if [ -n "$NPM_REGISTRY" ]; then npm config set registry "$NPM_REGISTRY"; fi \
 && npm config set fetch-retries 5 \
 && npm ci --omit=dev --no-audit --no-fund \
 && npm cache clean --force

# test.html — страница-тестер на GET /test, работает только при
# ENABLE_TEST_PAGE=true (SMS_GATEWAY_TEST_PAGE в .env.production)
COPY index.js test.html ./

USER node
EXPOSE 3000
CMD ["node", "index.js"]
