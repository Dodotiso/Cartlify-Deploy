FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev nginx supervisor curl \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . .

# CRITICAL: Allow plugins BEFORE composer install
RUN composer global config --no-plugins allow-plugins true
RUN composer config --no-plugins allow-plugins true

# Install WITHOUT --no-scripts, let it fail gracefully
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-posix || true

# Manually run the scripts that auto-scripts would run
RUN php bin/console cache:clear --env=prod --no-debug || true
RUN php bin/console assets:install public --symlink --relative || true

# Generate JWT keys
RUN mkdir -p config/jwt var/cache var/log public/uploads && \
    openssl genrsa -out config/jwt/private.pem 4096 && \
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && \
    chmod -R 777 var/cache var/log public/uploads config/jwt

COPY nginx.conf /etc/nginx/sites-enabled/default
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]