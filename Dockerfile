ARG PHP_SHORT_VERSION=84

FROM php:8.4 AS php-84
FROM php:8.5 AS php-85

FROM php-${PHP_SHORT_VERSION}

RUN docker-php-ext-install pdo pdo_mysql
RUN apt update && apt install -y bash default-mysql-client

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
