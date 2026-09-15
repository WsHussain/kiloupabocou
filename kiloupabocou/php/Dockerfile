FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY conf.d/symfony.ini /usr/local/etc/php/conf.d/symfony.ini

WORKDIR /var/www/symfony

CMD ["php-fpm"]
