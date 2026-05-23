# syntax=docker/dockerfile:1

FROM php:8.2-apache

# Install system dependencies required for PHP extensions and runtime tooling
RUN set -eux; \
    apt-get update; \
    apt-get install -y \
        bash \
        ca-certificates \
        curl \
        git \
        rsync \
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

# Install Docker CLI and Docker Compose v2 plugin
RUN set -eux; \
    curl -sfL https://download.docker.com/linux/static/stable/x86_64/docker-24.0.7.tgz -o /tmp/docker.tgz; \
    tar -xzf /tmp/docker.tgz -C /tmp; \
    mv /tmp/docker/docker /usr/local/bin/docker; \
    rm -rf /tmp/docker /tmp/docker.tgz; \
    mkdir -p /usr/local/lib/docker/cli-plugins; \
    curl -sfL https://github.com/docker/compose/releases/download/v2.24.5/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose; \
    chmod +x /usr/local/lib/docker/cli-plugins/docker-compose; \
    ln -s /usr/local/lib/docker/cli-plugins/docker-compose /usr/local/bin/docker-compose;

# Configure git system-wide to avoid ownership issues in mounted folders
RUN git config --system --add safe.directory '*'


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
    install -d -m 0775 -o www-data -g www-data \
        /var/www/html/data/code_backups \
        /var/www/html/storage/logs; \
    chmod +x /var/www/html/scripts/update.sh /var/www/html/scripts/update_check.sh; \
    chown -R www-data:www-data /var/www/html; \
    find /var/www/html -type d -exec chmod 0755 {} \;; \
    find /var/www/html/uploads -type d -exec chmod 0775 {} \;

# Copy entrypoint script and ensure it is executable
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod 755 /usr/local/bin/docker-entrypoint.sh

EXPOSE 2266

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
