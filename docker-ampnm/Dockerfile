# syntax=docker/dockerfile:1

FROM php:8.2-apache

# Install system dependencies required for PHP extensions and runtime tooling
RUN set -eux; \
    apt-get update; \
    apt-get install -y \
        curl \
        git \
        nmap \
        unzip \
        iputils-ping \
        net-tools \
        dnsutils \
        iproute2 \
        default-mysql-client \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        pkg-config; \
    rm -rf /var/lib/apt/lists/*

# Compile and enable the required PHP extensions
RUN set -eux; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        exif \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip

# Enable Apache's mod_rewrite for pretty URLs
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy application source
COPY . .

# Ensure the uploads directories exist with writable permissions
RUN set -eux; \
    install -d -m 0775 -o www-data -g www-data uploads \
        uploads/icons \
        uploads/map_backgrounds \
        uploads/backups; \
    chown -R www-data:www-data /var/www/html; \
    find /var/www/html -type d -exec chmod 0755 {} \;; \
    find /var/www/html/uploads -type d -exec chmod 0775 {} \;

# Copy entrypoint script and ensure it is executable
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod 755 /usr/local/bin/docker-entrypoint.sh

EXPOSE 2266

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
