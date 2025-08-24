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
ARG USER=userApp

# 8) Create user non-root & set permission
ARG UID=1000
ARG GID=1000
ARG USER=userApp
ARG GROUP=userGroup

ENV USER=${USER} \
  UID=${UID} \
  GROUP=${GROUP} \
  GID=${GID}

RUN addgroup -g ${GID} ${GROUP} \
  && adduser -D -u ${UID} -G ${GROUP} -h /home/${USER} ${USER} \
  && adduser ${USER} www-data \
  && mkdir -p /home/${USER}/.composer \
  && mkdir -p /var/www/storage /var/www/bootstrap/cache \
  && chown -R ${USER}:${GROUP} /var/www/storage /var/www/bootstrap/cache /home/${USER} \
  && chmod -R 755 /var/www/storage /var/www/bootstrap/cache
# 9) Switch to non-root user
USER ${USER}

# 10) Expose port & default command
CMD php artisan storage:link && \
  php artisan config:cache && \
  php-fpm