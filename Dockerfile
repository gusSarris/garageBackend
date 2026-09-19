FROM php:8.4-fpm

# Εγκατάσταση απαραίτητων πακέτων και extensions για Symfony & PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libpq-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

# Αντιγραφή του Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html