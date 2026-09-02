FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM dunglas/frankenphp:1-php8.4-alpine
RUN install-php-extensions mbstring pdo_pgsql pdo_sqlite pcntl redis
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY Caddyfile /etc/caddy/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/vive-backend-entrypoint
RUN rm -f bootstrap/cache/*.php && php artisan package:discover --ansi \
    && setcap -r /usr/local/bin/frankenphp \
    && chmod +x /usr/local/bin/vive-backend-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache /data /config \
    && chmod -R 775 storage bootstrap/cache
EXPOSE 8000
HEALTHCHECK NONE
USER www-data
CMD ["vive-backend-entrypoint"]
