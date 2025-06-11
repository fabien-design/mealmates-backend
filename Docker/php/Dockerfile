FROM php:8.4-fpm

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Add PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions fileinfo intl mbstring pdo_mysql zip

COPY Docker/php/php.ini /usr/local/etc/php/conf.d/uploads.ini

RUN apt-get update && \
    apt update && \
    apt install default-mysql-client -y && \
    docker-php-ext-install pdo_mysql

RUN apt-get install -y git unzip