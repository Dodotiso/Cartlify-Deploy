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

# Copy composer files first and install as root (but without scripts)
COPY composer.json composer.lock ./

# Install as root first to create vendor directory
RUN composer install --no-interaction --optimize-autoloader --no-scripts --ignore-platform-req=ext-posix

# Now copy all files
COPY --chown=appuser:appuser . .

# Fix permissions
RUN chown -R appuser:appuser /app

# Switch to non-root user for the rest
USER appuser

# Now run the full install (scripts will run as non-root)
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-req=ext-posix

RUN mkdir -p var/cache var/log

# Switch back to root for nginx
USER root

COPY nginx-main.conf /etc/nginx/nginx.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]