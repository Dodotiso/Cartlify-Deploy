#!/bin/sh
set -e

echo "🚀 Starting Cartlify on Railway..."

# Use Railway's variables directly
DB_HOST="${MYSQLHOST}"
DB_PORT="${MYSQLPORT}"
DB_USER="${MYSQLUSER}"
DB_PASS="${MYSQLPASSWORD}"
DB_NAME="${MYSQLDATABASE}"

echo "Connecting to database at $DB_HOST:$DB_PORT"

# Wait for database connection
max_retries=30
counter=0

while [ $counter -lt $max_retries ]; do
    if php -r "new PDO('mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME', '$DB_USER', '$DB_PASS');" 2>/dev/null; then
        echo "✅ Database is ready!"
        break
    fi
    counter=$((counter + 1))
    echo "Waiting for database... ($counter/$max_retries)"
    sleep 3
done

if [ $counter -ge $max_retries ]; then
    echo "❌ Could not connect to database after $max_retries attempts"
    exit 1
fi

# Run migrations
echo "📦 Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Clear cache
echo "🧹 Clearing cache..."
php bin/console cache:clear --env=prod --no-debug

echo "✅ Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf