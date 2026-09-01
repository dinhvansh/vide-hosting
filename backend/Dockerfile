FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM dunglas/frankenphp:1-php8.4-alpine
RUN install-php-extensions pdo_pgsql pcntl redis
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY Caddyfile /etc/caddy/Caddyfile
RUN rm -f bootstrap/cache/*.php && php artisan package:discover --ansi \
    && setcap -r /usr/local/bin/frankenphp \
    && chown -R www-data:www-data storage bootstrap/cache /data /config \
    && chmod -R 775 storage bootstrap/cache
EXPOSE 8000
HEALTHCHECK NONE
USER www-data
CMD ["sh", "-c", "php artisan migrate --force && frankenphp run --config /etc/caddy/Caddyfile"]
