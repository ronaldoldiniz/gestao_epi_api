FROM php:8.1-apache

RUN a2enmod rewrite && a2enmod headers

RUN docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
