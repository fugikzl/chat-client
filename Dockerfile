FROM php:8.3-cli

WORKDIR /app

RUN apt-get update && apt-get install -y unzip git libzip-dev \
    && docker-php-ext-install pcntl

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


COPY . /app

RUN composer install --no-dev --optimize-autoloader


CMD ["php", "client.php", "start"]
