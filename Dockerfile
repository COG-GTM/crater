FROM php:8.1-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libmagickwand-dev \
    ghostscript \
    mariadb-client

# Upload2Shell (CVE-2026-125396, DSA-6516-1): fail the build if Ghostscript is older than the patched Debian build
RUN for pkg in ghostscript libgs10; do \
        v="$(dpkg-query -W -f='${Version}' "$pkg" 2>/dev/null)"; \
        dpkg --compare-versions "$v" ge '10.05.1~dfsg-1+deb13u2' \
            || { echo "$pkg '$v' is vulnerable to CVE-2026-125396; need >= 10.05.1~dfsg-1+deb13u2" >&2; exit 1; }; \
    done

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www

USER $user
