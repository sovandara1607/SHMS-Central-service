# syntax=docker/dockerfile:1
#
# Mirrors Database-final/Dockerfile's build shape (composer -> node assets ->
# php:8.4-cli-alpine runtime with native mongodb/redis extensions), but the
# runtime here has to keep three long-running processes alive together
# instead of one:
#   - php artisan serve       (REST API: /api/health, /api/lab-reports/*)
#   - php artisan bus:relay   (drains the shared Redis bus)
#   - php artisan queue:work  (processes the jobs bus:relay dispatches)
# supervisord owns that instead of a plain `sh -c 'a & b & c'`, so a crashed
# worker actually gets restarted rather than silently staying dead.
#
# Per README.md: this app never runs `php artisan migrate` — Postgres schema
# ownership stays with Database-final.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader \
        --ignore-platform-req=ext-mongodb --ignore-platform-req=ext-redis
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------- 2. Build frontend assets ----------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

# ---------- 3. Runtime image ----------
FROM php:8.4-cli-alpine
WORKDIR /var/www/html

RUN apk add --no-cache supervisor

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo pdo_pgsql zip intl bcmath opcache mongodb redis

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 8100

# No migrate here (see README.md: schema ownership stays with Database-final).
# config/route/view caching is safe — this app's routes/config don't vary by request.
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && exec supervisord -c /etc/supervisord.conf"]
