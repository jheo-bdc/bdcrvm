FROM php:8.3-cli

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app
COPY . .
RUN mkdir -p storage/recordings

CMD php -S 0.0.0.0:${PORT:-8080} -t /app
