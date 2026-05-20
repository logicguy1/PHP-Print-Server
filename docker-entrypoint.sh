#!/bin/sh
set -e

chown -R www-data:www-data /var/www/data /var/www/html/public/uploads
chmod 755 /var/www/data /var/www/html/public/uploads

exec apache2-foreground
