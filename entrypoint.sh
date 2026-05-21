#!/bin/sh
set -e

echo "🚀 Starting Cartlify on Railway..."

# Debug: Print variables (remove after debugging)
echo "MySQL Host: ${MYSQLHOST}"
echo "MySQL Port: ${MYSQLPORT}"
echo "MySQL User: ${MYSQLUSER}"
echo "MySQL Database: ${MYSQLDATABASE}"
echo "MySQL Password exists: $(if [ -n "${MYSQLPASSWORD}" ]; then echo 'YES'; else echo 'NO'; fi)"

# Check if password is empty
if [ -z "${MYSQLPASSWORD}" ]; then
    echo "❌ ERROR: MYSQLPASSWORD is not set!"
    exit 1
fi

echo "⏳ Waiting for database..."

max_retries=30
counter=0

until php -r "
try {
    \$pdo = new PDO('mysql:host=${MYSQLHOST};port=${MYSQLPORT};dbname=${MYSQLDATABASE}', '${MYSQLUSER}', '${MYSQLPASSWORD}');
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