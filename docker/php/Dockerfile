# Используем официальный образ PHP 8.3 с веб-сервером на Alpine
FROM php:8.3-cli-alpine

# Устанавливаем системные зависимости с помощью apk add
RUN apk add --no-cache mysql-client

# Устанавливаем необходимые расширения PHP с помощью docker-php-ext-install
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Копируем наш php.ini в контейнер
COPY php.ini /usr/local/etc/php/php.ini

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

# Запускаем встроенный веб-сервер PHP на порту 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]
