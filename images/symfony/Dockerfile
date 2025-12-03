# Context need to be set to the root of the project "../.."
FROM php:8.5-fpm

# Install php-pgsql, composer, composer dependencies and upgrade database schema
RUN apt-get update && apt-get install -y \
    git \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    zip

RUN docker-php-ext-install zip && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl && \
    docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install pdo_pgsql pgsql

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.* /var/www/html/
COPY .env* /var/www/html/
COPY symfony.lock /var/www/html/
COPY migrations /var/www/html/migrations
COPY bin /var/www/html/bin

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer install --no-scripts

COPY config /var/www/html/config
COPY public /var/www/html/public
COPY src /var/www/html/src
COPY templates /var/www/html/templates

# composer install --no-dev --optimize-autoloader
# Do not exit on failing composer install for development convenience
# When already having a built container, this will allow restarts
# of the container without internet access
CMD php bin/console doctrine:migrations:migrate && \
    (php bin/console lexik:jwt:generate-keypair || echo "No keys were generated!") && \
    (composer install || echo "Composer install was not successful!") && \
    php-fpm
