#!/bin/sh
set -e

if [ "$1" = 'php' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi
fi

exec docker-php-entrypoint "$@"
