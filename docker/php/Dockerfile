# Optimized Multi-stage Dockerfile for PHP 8.4 with Symfony 7
# Minimal dependencies - removed C/C++ build tools

# Stage 1: Builder
FROM php:8.4-fpm-alpine AS builder

# Install only essential build dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    rabbitmq-c-dev \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        intl \
        opcache \
        zip \
        bcmath

# Install PECL extensions
RUN pecl install amqp-2.1.1 redis-6.0.2 \
    && docker-php-ext-enable amqp redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files
COPY composer.json ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Stage 2: Development
FROM builder AS development

# Install Xdebug for development
RUN pecl install xdebug-3.3.1 \
    && docker-php-ext-enable xdebug

# Configure Xdebug
RUN echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]

# Stage 3: Production
FROM php:8.4-fpm-alpine AS production

# Install only runtime libraries (no build tools)
RUN apk add --no-cache \
    postgresql-libs \
    libzip \
    icu-libs \
    rabbitmq-c

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www

# Copy vendor from builder
COPY --from=builder /var/www/vendor ./vendor

# Copy application
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/var

EXPOSE 9000

CMD ["php-fpm"]