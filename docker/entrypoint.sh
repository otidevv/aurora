#!/bin/sh
set -e

# The moodledata named volume is created owned by root; Moodle (Apache/www-data)
# must be able to write to it. Fix ownership on every start (cheap, idempotent).
mkdir -p /var/www/moodledata
chown -R www-data:www-data /var/www/moodledata

exec "$@"
