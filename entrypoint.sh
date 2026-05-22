#!/bin/bash
set -e

echo "=== Starting Cartlify on Railway ==="

# Wait for MySQL to be ready
echo "Waiting for database connection..."
ATTEMPTS=0
MAX_ATTEMPTS=30
until php -r "
try {
    \$host = getenv('MYSQLHOST');
    \$port = getenv('MYSQLPORT') ?: '3306';
    \$dbname = getenv('MYSQLDATABASE');
    \$user = getenv('MYSQLUSER');
    \$pass = getenv('MYSQLPASSWORD');
    
    new PDO(
        \"mysql:host=\$host;port=\$port;dbname=\$dbname\",
        \$user,
        \$pass,
        [PDO::ATTR_TIMEOUT => 5]
    );
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ $ATTEMPTS -ge $MAX_ATTEMPTS ]; then
        echo "Failed to connect to database after $MAX_ATTEMPTS attempts"
        break
    fi
    echo "Waiting... ($ATTEMPTS/$MAX_ATTEMPTS)"
    sleep 2
done

# Generate JWT keys if missing
if [ ! -f /app/config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    mkdir -p /app/config/jwt
    openssl genrsa -out /app/config/jwt/private.pem 4096
    openssl rsa -pubout -in /app/config/jwt/private.pem -out /app/config/jwt/public.pem
    chmod 644 /app/config/jwt/*.pem
fi

# Clear and warmup cache for production
echo "Clearing and warming cache..."
php /app/bin/console cache:clear --env=prod --no-debug || true
php /app/bin/console cache:warmup --env=prod || true

# Run database migrations
echo "Running database migrations..."
php /app/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

# Start services
echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
nginx -g "daemon off;"