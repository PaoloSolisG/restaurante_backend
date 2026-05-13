FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash curl libzip-dev libpng-dev libjpeg-turbo-dev \
    freetype-dev icu-dev oniguruma-dev linux-headers

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mbstring zip bcmath intl gd pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
