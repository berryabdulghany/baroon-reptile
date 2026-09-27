FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers

COPY docker/apache.conf /etc/apache2/conf-available/zz-baroon.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/baroon.ini
RUN a2enconf zz-baroon

COPY --chown=www-data:www-data . /var/www/html/
