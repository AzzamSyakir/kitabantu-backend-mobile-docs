# Stage 1: Build
FROM php:8.4.10-fpm-alpine AS build

ARG UID=1000
ARG GID=1000
ARG USER=userApp
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache \
  linux-headers \
  libressl-dev \
  rabbitmq-c-dev \
  zlib-dev \
  libxml2-dev \
  oniguruma-dev \
  autoconf \
  gcc \
  g++ \
  make \
  bash \
  curl \
  && docker-php-ext-install pdo_mysql sockets

WORKDIR /app
COPY composer.json composer.lock ./
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction \
  --no-scripts

COPY . .

RUN addgroup -g ${GID} ${USER} \
  && adduser -D -u ${UID} -G ${USER} ${USER} \
  && mkdir -p /var/www/storage /var/www/bootstrap/cache /home/${USER}/.composer \
  && chown -R ${USER}:${USER} /var/www/storage /var/www/bootstrap/cache /home/${USER} /app \
  && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Stage 1: runtime
FROM php:8.4.10-fpm-alpine AS runtime

RUN apk add --no-cache libressl rabbitmq-c

WORKDIR /var/www
COPY --from=build /app /var/www

ARG UID=1000
ARG GID=1000
ARG USER=userApp
RUN addgroup -g ${GID} ${USER} \
  && adduser -D -u ${UID} -G ${USER} ${USER} \
  && chown -R ${USER}:${USER} /var/www/storage /var/www/bootstrap/cache

USER ${USER}

CMD php artisan storage:link && \
  php artisan config:cache && \
  php-fpm
