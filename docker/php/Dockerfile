FROM php:8.3-fpm-alpine

# Системные зависимости
RUN apk add --no-cache \
    bash git curl zip unzip \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev icu-dev oniguruma-dev \
    autoconf g++ make linux-headers

# PHP-расширения
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo_mysql mysqli mbstring exif pcntl bcmath gd zip intl opcache sockets

# Redis
RUN pecl channel-update pecl.php.net \
 && pecl install redis \
 && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Совпадение UID с хостом, чтобы не ловить права на storage/
ARG UID=1000
ARG GID=1000
RUN deluser www-data 2>/dev/null || true \
 && addgroup -g ${GID} www-data \
 && adduser -u ${UID} -G www-data -s /bin/sh -D www-data

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
