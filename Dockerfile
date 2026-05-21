FROM php:8.3-fpm

# Create a non-root user
RUN useradd -m -u 1000 -s /bin/bash appuser

RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY --chown=appuser:appuser . .

# Run as non-root user
USER appuser

RUN composer install --no-interaction --optimize-autoloader --ignore-platform-req=ext-posix

RUN mkdir -p var/cache var/log && chmod -R 777 var

# Switch back to root for nginx (needs root)
USER root

COPY nginx-main.conf /etc/nginx/nginx.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]