#!/bin/sh

set -eu

attempt=1
max_attempts=30

until php artisan migrate --force; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "Database did not become ready after $max_attempts migration attempts." >&2
        exit 1
    fi

    echo "Database is not ready; retrying migration in 2 seconds ($attempt/$max_attempts)." >&2
    attempt=$((attempt + 1))
    sleep 2
done

exec frankenphp run --config /etc/caddy/Caddyfile
