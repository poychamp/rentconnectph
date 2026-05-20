#!/bin/sh
set -e

mkdir -p /var/www/html/vendor /var/www/html/storage /var/www/html/bootstrap/cache
chown -R app:app /var/www/html/vendor /var/www/html/storage /var/www/html/bootstrap/cache

exec gosu app "$@"
