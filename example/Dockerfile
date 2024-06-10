FROM php:cli-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    install-php-extensions \
        mongodb \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
    ;

WORKDIR /app

COPY --link --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php", "main.php"]
