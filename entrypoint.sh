#!/bin/bash
set -e

echo "=== Starting Cartlify on Railway ==="

# Create .env file from Railway environment variables
echo "Creating .env from environment variables..."
cat > /app/.env << ENVEOF
APP_ENV=prod
APP_SECRET=${APP_SECRET}
DATABASE_URL="mysql://${MYSQLUSER}:${MYSQLPASSWORD}@${MYSQLHOST}:${MYSQLPORT}/${MYSQLDATABASE}?serverVersion=8.0&charset=utf8mb4"
CORS_ALLOW_ORIGIN=${CORS_ALLOW_ORIGIN}
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}
GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}
MAILER_DSN=${MAILER_DSN}
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=${JWT_PASSPHRASE}
ENVEOF

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