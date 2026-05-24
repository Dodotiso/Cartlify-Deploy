FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    git \
    unzip \
    openssl \
    procps \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY . .

# Install production dependencies only
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Create minimal .env for asset compilation
RUN echo "APP_ENV=prod" > /app/.env && \
    echo "APP_SECRET=placeholder" >> /app/.env && \
    echo "DATABASE_URL=mysql://root:root@localhost:3306/app?serverVersion=8.0" >> /app/.env

# Compile assets
RUN php bin/console importmap:install --no-interaction
RUN APP_ENV=prod php bin/console asset-map:compile --no-interaction

# Remove temporary .env (entrypoint will create the real one)
RUN rm /app/.env

# Create directories and set ownership to www-data
RUN mkdir -p var/cache var/log config/jwt && \
    chown -R www-data:www-data var config/jwt && \
    chmod -R 775 var config/jwt

COPY nginx-main.conf /etc/nginx/nginx.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]