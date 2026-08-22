# ========================================================
# Indra Hotel CMS - Production Dockerfile
# Base: PHP 8.2 with Apache
# ========================================================
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies & libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libonig-dev \
    sqlite3 \
    libsqlite3-dev \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension with support for FreeType, JPEG, and WebP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# Install required PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    gd \
    pdo_mysql \
    pdo_sqlite \
    zip \
    opcache \
    mbstring

# Enable Apache modules for clean URLs & security
RUN a2enmod rewrite headers

# Production PHP Configuration
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'upload_max_filesize=128M'; \
    echo 'post_max_size=128M'; \
    echo 'memory_limit=256M'; \
    echo 'max_execution_time=300'; \
    echo 'date.timezone=Asia/Phnom_Penh'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_log=/dev/stderr'; \
} > /usr/local/etc/php/conf.d/indra-production.ini

# Custom Apache Configuration for Security & Clean URLs
RUN { \
    echo '<Directory /var/www/html>'; \
    echo '    Options -Indexes +FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '    Header always set Content-Security-Policy "upgrade-insecure-requests"'; \
    echo '</Directory>'; \
    echo 'ServerTokens Prod'; \
    echo 'ServerSignature Off'; \
} > /etc/apache2/conf-available/security-override.conf \
&& a2enconf security-override

# Copy Application Source Code
COPY . /var/www/html/

# Ensure proper permissions for www-data user
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads

# Expose HTTP port
EXPOSE 80

# Apache default command
CMD ["apache2-foreground"]
