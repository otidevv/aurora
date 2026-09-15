# Moodle 5.2 requires PHP 8.3+ (see public/admin/environment.xml)
FROM php:8.3-apache

# Install all PHP extensions Moodle needs (gd, intl, mbstring, zip, soap, exif,
# opcache and the native PostgreSQL driver). The helper image resolves all the
# system library dependencies automatically.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd intl mbstring zip soap exif opcache pgsql

# Apache: enable rewrite/headers and point the docroot at Moodle's public/ dir.
RUN a2enmod rewrite headers
COPY docker/apache-moodle.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-moodle.ini

# Ensure the moodledata volume is writable by Apache before the server starts.
COPY docker/entrypoint.sh /usr/local/bin/moodle-entrypoint.sh
RUN chmod +x /usr/local/bin/moodle-entrypoint.sh

WORKDIR /var/www/html
ENTRYPOINT ["/usr/local/bin/moodle-entrypoint.sh"]
CMD ["apache2-foreground"]
