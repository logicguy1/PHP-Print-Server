FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    cups-client \
    libsqlite3-dev \
    img2pdf \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN printf "upload_max_filesize = 512M\npost_max_size = 512M\n" \
    > /usr/local/etc/php/conf.d/uploads.ini

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /var/www/data /var/www/html/public/uploads

ENV CUPS_SERVER=/var/run/cups/cups.sock

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
