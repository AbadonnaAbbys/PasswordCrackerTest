# Using the official PHP 8.3 image with web server on Alpine
FROM php:8.3-cli-alpine

# Install system dependencies using apk add
RUN apk add --no-cache mysql-client

# Install required PHP extensions using docker-php-ext-install
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy our php.ini into the container
COPY php.ini /usr/local/etc/php/php.ini

# Set the working directory
WORKDIR /var/www/html

# Start the built-in PHP web server on port 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]
