ARG PHP_SHORT_VERSION=82

FROM php:8.2-alpine AS php-82
FROM php:8.3-alpine AS php-83
FROM php:8.4-alpine AS php-84

FROM php-${PHP_SHORT_VERSION}

RUN docker-php-ext-install pdo pdo_mysql
RUN apk --update --no-cache add bash mysql-client

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
