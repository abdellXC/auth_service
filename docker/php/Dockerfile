# Optimized Multi-stage Dockerfile for PHP 8.4 with Symfony 7

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
        pgsql \
        intl \
        opcache \
        zip \
        bcmath

# Install PECL extensions (use latest versions compatible with PHP 8.4)
RUN pecl install amqp redis \
    && docker-php-ext-enable amqp redis

# Clean up build dependencies to reduce image size
RUN apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear /var/cache/apk/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 1. CORRECTION : Copier composer.json AVANT d'installer
COPY composer.json composer.lock* ./

# 2. CORRECTION : Installation des dépendances
# Supprimé la ligne "RUN composer install..." qui était ici et inutile
RUN XDEBUG_MODE=off composer update --no-dev --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs \
    && composer clear-cache \
    && rm -rf /root/.composer/cache

# Stage 2: Development
FROM builder AS development

# Reinstall build deps for Xdebug (including linux-headers)
RUN apk add --no-cache $PHPIZE_DEPS linux-headers

# Install Xdebug for development
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /tmp/pear /var/cache/apk/*

# Configure Xdebug
RUN echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]

# Stage 3: Production (Ultra-optimized)
FROM php:8.4-fpm-alpine AS production

# Install only runtime libraries (no build tools)
RUN apk add --no-cache \
    postgresql-libs \
    libzip \
    icu-libs \
    rabbitmq-c \
    && rm -rf /var/cache/apk/*

# 3. CORRECTION : Copier les scripts helpers ET les extensions
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/
COPY --from=builder /usr/local/lib/php/extensions/*/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www

# Copy vendor from builder
COPY --from=builder /var/www/vendor ./vendor

# Copy application (exclude unnecessary files)
COPY --chown=www-data:www-data bin ./bin
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data migrations ./migrations
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data src ./src
COPY --chown=www-data:www-data templates ./templates
COPY --chown=www-data:www-data .env ./

# Create necessary directories
RUN mkdir -p var/cache var/log var/jwt \
    && chown -R www-data:www-data var \
    && chmod -R 755 var

# Remove unnecessary files
RUN rm -rf tests/ docs/ .git/ .github/ *.md

EXPOSE 9000

CMD ["php-fpm"]
