#!/bin/bash
set -e

echo "=== Starting Cartlify on Railway ==="

echo "=== Checking images ==="
ls /app/public/images/ 2>&1 || echo "Images folder NOT FOUND"
echo "=== End images check ==="

# Create .env file from Railway environment variables
echo "Creating .env from environment variables..."
cat > /app/.env << ENVEOF
APP_ENV=prod
APP_SECRET=${APP_SECRET}
DEFAULT_URI=https://${RAILWAY_PUBLIC_DOMAIN:-localhost}
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

echo ".env file created successfully"

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
php /app/bin/console cache:clear --env=prod --no-debug 2>&1 || echo "Cache clear warning (non-fatal)"
php /app/bin/console cache:warmup --env=prod 2>&1 || echo "Cache warmup warning (non-fatal)"

# Fix permissions after cache operations
echo "Fixing filesystem permissions..."
chown -R www-data:www-data /app/var
chmod -R 775 /app/var

# Wait for database and run migrations
if [ ! -z "$MYSQLHOST" ]; then
    echo "Waiting for database connection..."
    for i in {1..20}; do
        if php -r "
        try {
            new PDO('mysql:host=${MYSQLHOST};port=${MYSQLPORT:-3306};dbname=${MYSQLDATABASE}', '${MYSQLUSER}', '${MYSQLPASSWORD}', [PDO::ATTR_TIMEOUT => 3]);
            echo 'connected';
            exit(0);
        } catch (Exception \$e) { exit(1); }
        " 2>/dev/null; then
            echo "Database connected!"
            
            # Try running migrations normally
            echo "Running migrations..."
            if php /app/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod 2>&1; then
                echo "All migrations completed successfully!"
            else
                echo "Some migrations failed. Skipping problematic migration..."
                # Mark the broken migration as executed and continue
                php /app/bin/console doctrine:migrations:version 'DoctrineMigrations\Version20260318094426' --add --no-interaction --env=prod 2>&1 || true
                echo "Continuing with remaining migrations..."
                php /app/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod 2>&1 || true
            fi
            
            # Fix permissions after migrations
            echo "Fixing permissions after migrations..."
            chown -R www-data:www-data /app/var
            chmod -R 775 /app/var
            break
        fi
        echo "Waiting... ($i/20)"
        sleep 3
    done
else
    echo "No MySQL service detected. Skipping database setup."
fi

# Replace ${PORT} in nginx config with actual Railway PORT
export PORT=${PORT:-80}
echo "Configuring Nginx to listen on port $PORT..."
sed -i "s/\${PORT}/$PORT/g" /etc/nginx/conf.d/default.conf

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm -D

# Wait and verify PHP-FPM started
sleep 2
if ps aux | grep -v grep | grep php-fpm > /dev/null; then
    echo "PHP-FPM is running"
else
    echo "WARNING: PHP-FPM may not have started properly"
fi

echo "Starting Nginx on port $PORT..."
nginx -g "daemon off;"