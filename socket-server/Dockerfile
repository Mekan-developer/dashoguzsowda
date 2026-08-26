FROM node:22-alpine
WORKDIR /var/www/socket-server
COPY package*.json ./
RUN npm ci --omit=dev
COPY . .
CMD ["node", "index.js"]
