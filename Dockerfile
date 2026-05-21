FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Force allow plugins globally
RUN composer global config --no-plugins allow-plugins true

WORKDIR /app

COPY . .

# Run composer as root but allow plugins
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-req=ext-posix || true

# Run the scripts manually
RUN php bin/console cache:clear --env=prod --no-debug || true
RUN php bin/console assets:install public --symlink --relative || true

RUN mkdir -p var/cache var/log && chmod -R 777 var

COPY nginx-main.conf /etc/nginx/nginx.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]