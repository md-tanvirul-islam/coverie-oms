FROM php:8.4-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql mbstring bcmath gd zip opcache pcntl intl exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

RUN sed -i \
        -e 's/^listen = .*/listen = 0.0.0.0:9000/' \
        /usr/local/etc/php-fpm.d/www.conf

# Don't leak the PHP version / display raw errors to the outside world.
RUN { \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'display_startup_errors = Off'; \
    } > /usr/local/etc/php/conf.d/zz-security.ini

WORKDIR /var/www/coverie-oms
EXPOSE 9000
