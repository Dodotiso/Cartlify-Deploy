FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev nginx supervisor curl \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . .

# Fix: Disable symfony-cmd auto-scripts and run as root
RUN composer config --global allow-plugins true
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-posix --no-scripts

# Run Symfony scripts manually after install
RUN php bin/console cache:clear --env=prod --no-debug || true

RUN mkdir -p config/jwt var/cache var/log public/uploads && \
    chmod -R 777 var/cache var/log public/uploads config/jwt

COPY nginx.conf /etc/nginx/sites-enabled/default
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]