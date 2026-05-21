FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy application
COPY . .

# Install dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Generate JWT keys if not exist
RUN mkdir -p config/jwt && \
    openssl genrsa -out config/jwt/private.pem 4096 && \
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && \
    chmod 644 config/jwt/*.pem

# Set permissions
RUN chmod -R 777 var/cache var/log

# Copy nginx configs
COPY nginx-main.conf /etc/nginx/sites-enabled/default

# Copy supervisor config
COPY supervisor.conf /etc/supervisor/conf.d/supervisor.conf

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]