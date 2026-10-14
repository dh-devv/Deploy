FROM php:8.2-fpm 
RUN docker-php-ext-install mysqli pdo_mysql
WORKDIR /var/www/html

COPY ./src /var/www/html/
COPY ./docker/php.ini /usr/local/etc/php/conf.d/board.ini
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html
