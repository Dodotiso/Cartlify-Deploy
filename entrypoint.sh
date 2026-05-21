#!/bin/sh
set -e

echo "🚀 Starting Cartlify on Railway..."

echo "⏳ Waiting for database..."
max_retries=30
counter=0
until php -r "try { new PDO('mysql:host=${MYSQLHOST}:${MYSQLPORT}', '${MYSQLUSER}', '${MYSQLPASSWORD}'); echo 'Connected'; } catch (Exception \$e) { exit(1); }" > /dev/null 2>&1; do
    counter=$((counter + 1))
    if [ $counter -gt $max_retries ]; then
        echo "❌ Database connection failed"
        exit 1
    fi
    echo "Waiting for MySQL... ($counter/$max_retries)"
    sleep 3
done
echo "✅ Database ready!"

echo "📦 Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

echo "🧹 Clearing cache..."
php bin/console cache:clear --env=prod --no-debug

echo "✅ Deployment complete!"
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf