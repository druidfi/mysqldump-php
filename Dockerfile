ARG PHP_SHORT_VERSION=84

FROM php:8.4 AS php-84
FROM php:8.5 AS php-85
FROM php:8.6 AS php-86

FROM php-${PHP_SHORT_VERSION}

RUN apt update && apt install -y bash default-mysql-client libonig-dev
RUN docker-php-ext-install pdo pdo_mysql
# PHPUnit needs ext-mbstring; the official php:8.6 image no longer bundles it
# (8.4/8.5 do). The library itself does not depend on it.
RUN php -m | grep -qx mbstring || docker-php-ext-install mbstring

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
