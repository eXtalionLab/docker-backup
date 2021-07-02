FROM php:cli-alpine

RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
    ; \
    \
    docker-php-ext-install -j$(nproc) \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
    ; \
    \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache --virtual .api-phpexts-rundeps $runDeps; \
    \
    apk del .build-deps

COPY ./src /app

CMD ["php", "/app/app.php"]
