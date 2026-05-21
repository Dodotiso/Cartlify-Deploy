#!/bin/sh
set -e

echo "🚀 Starting Cartlify on Railway..."

# Wait for Railway's MySQL to be ready
echo "⏳ Waiting for database..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
    echo "Waiting for MySQL..."
    sleep 3
done
echo "✅ Database is ready!"

# Run migrations
echo "📦 Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

# Clear and warmup cache
echo "🧹 Clearing cache..."
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod

echo "✅ Deployment complete! Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf