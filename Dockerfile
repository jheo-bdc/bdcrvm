FROM dunglas/frankenphp:latest

# Install MySQL PDO driver
RUN install-php-extensions pdo_mysql

WORKDIR /app
COPY . .

RUN mkdir -p storage/recordings

# Place our Caddyfile where FrankenPHP expects it
COPY Caddyfile /Caddyfile
