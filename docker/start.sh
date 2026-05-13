#!/bin/bash

set -e

role=${CONTAINER_ROLE:-app}
env=${APP_ENV:-production}

if [ "$env" != "local" ]; then
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

if [ "$role" = "app" ]; then

    echo "Running app with Swoole..."
    exec php artisan octane:start \
        --server=swoole \
        --host=0.0.0.0 \
        --port=8000 \
        --workers=auto \
        --max-requests=500

elif [ "$role" = "worker" ]; then

    echo "Running the queue worker..."
    exec php artisan queue:work redis --verbose --tries=3 --timeout=90

elif [ "$role" = "scheduler" ]; then

    echo "Running the scheduler..."
    while [ true ]
    do
      php artisan schedule:run --no-interaction &
      sleep 60
    done

else
    echo "Could not find role \"$role\""
    exit 1
fi