ARG PHP_SHORT_VERSION

FROM druidfi/php:7.4 AS php-74

RUN sudo apk --update -X https://dl-cdn.alpinelinux.org/alpine/edge/testing --no-cache add php7-pdo php7-pdo_mysql

FROM druidfi/php:8.0 AS php-80

RUN sudo apk --update --no-cache add php8-pdo php8-pdo_mysql

FROM druidfi/php:8.1 AS php-81

RUN sudo apk --update --no-cache add php81-pdo php81-pdo_mysql

FROM druidfi/php:8.2 AS php-82

RUN sudo apk --update -X https://dl-cdn.alpinelinux.org/alpine/edge/community --no-cache add php82-pdo php82-pdo_mysql

FROM druidfi/php:8.3 AS php-83

RUN sudo apk --update -X https://dl-cdn.alpinelinux.org/alpine/edge/community --no-cache add php83-pdo php83-pdo_mysql

FROM php-${PHP_SHORT_VERSION}

RUN sudo apk --update --no-cache add bash mysql-client \
    && sudo rm -rf /var/cache/apk/*

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
