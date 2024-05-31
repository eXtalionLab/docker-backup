FROM php:cli-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
    install-php-extensions \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
    ;

COPY ./src /app

CMD ["php", "/app/app.php"]
