# 1) Base image
FROM php:8.4.10-fpm-alpine

# 2) Build args & env
ARG user=user
ARG uid=1000
ENV USER=${user} \
  UID=${uid} \
  COMPOSER_ALLOW_SUPERUSER=1

# 3) Install system deps & PHP extensions
RUN apk add --no-cache \
  --no-cache linux-headers \
  libressl \
  libressl-dev \
  rabbitmq-c \
  rabbitmq-c-dev \
  zlib-dev \
  libxml2-dev \
  oniguruma-dev \
  autoconf \
  gcc \
  g++ \
  make \
  bash \
  && docker-php-ext-install pdo_mysql sockets

# 4) Set working directory
WORKDIR /var/www

# 5) Copy composer files dulu supaya caching layer maksimal
COPY composer.json composer.lock ./

# 6) Install Composer & dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction \
  --no-scripts

# 7) Copy entire source app
COPY . .

# 8) Create user non-root & set permission
ARG UID=1000
ARG USER=appuser

RUN addgroup -g ${UID} ${USER} \
  && adduser -D -u ${UID} -G ${USER} -h /home/${USER} ${USER} \
  && mkdir -p /home/${USER}/.composer \
  && mkdir -p /var/www/storage /var/www/bootstrap/cache \
  && chown -R ${USER}:${USER} /var/www/storage /var/www/bootstrap/cache /home/${USER}

# 9) Switch to non-root user
USER ${USER}


# 10) Expose port & default command
EXPOSE 9000