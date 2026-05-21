#!/bin/bash
set -e

echo "=== Starting Cartlify ==="

# Wait for database
echo "Waiting for database..."
sleep 5

# Generate JWT keys if missing
if [ ! -f /app/config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    mkdir -p /app/config/jwt
    openssl genrsa -out /app/config/jwt/private.pem 4096
    openssl rsa -pubout -in /app/config/jwt/private.pem -out /app/config/jwt/public.pem
    chmod 644 /app/config/jwt/*.pem
fi

# Run migrations
echo "Running migrations..."
php /app/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

# Clear cache
echo "Clearing cache..."
php /app/bin/console cache:clear --env=prod --no-debug || true

echo "Starting PHP-FPM..."
php-fpm -F