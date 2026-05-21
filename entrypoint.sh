#!/bin/sh
set -e

echo "🚀 Starting Cartlify on Railway..."
echo "⏳ Waiting for database..."

# Use Railway's MySQL variables
DB_HOST="${MYSQLHOST:-mysql.railway.internal}"
DB_PORT="${MYSQLPORT:-3306}"
DB_USER="${MYSQLUSER:-root}"
DB_PASSWORD="${MYSQLPASSWORD}"
DB_NAME="${MYSQLDATABASE:-railway}"

echo "Connecting to MySQL at $DB_HOST:$DB_PORT"

max_retries=30
counter=0

until php -r "
try {
    \$pdo = new PDO('mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME', '$DB_USER', '$DB_PASSWORD');
    echo 'Connected successfully';
    exit(0);
} catch (PDOException \$e) {
    echo 'Connection failed: ' . \$e->getMessage();
    exit(1);
}
" 2>&1; do
    counter=$((counter + 1))
    if [ $counter -gt $max_retries ]; then
        echo "❌ Database connection failed after $max_retries attempts"
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