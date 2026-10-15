# --- Frontend assets ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Ziggy's Vue plugin is imported from vendor/, so composer deps are needed for the build.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache php83 php83-phar php83-mbstring php83-openssl php83-tokenizer php83-curl php83-iconv \
    && ln -sf /usr/bin/php83 /usr/bin/php \
    && composer install --no-dev --no-scripts --no-interaction --ignore-platform-reqs \
    && npm run build

# --- App ---
FROM serversideup/php:8.3-fpm-nginx
USER root
RUN install-php-extensions bcmath gmp intl
USER www-data

WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && rm -f database/*.sqlite public/hot

# serversideup runs these on container start: config/route/view cache + migrations.
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    PHP_OPCACHE_ENABLE=1
