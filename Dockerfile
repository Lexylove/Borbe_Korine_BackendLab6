ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache

# PDO MySQL (needed for Aiven MySQL)
RUN docker-php-ext-install pdo pdo_mysql

# Apache: mod_rewrite for LavaLust routing, mod_headers
RUN a2enmod rewrite headers

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy app files
COPY . /var/www/html/

# Point Apache document root to public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot ${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/html>|<Directory ${APACHE_DOCUMENT_ROOT}>|g' /etc/apache2/apache2.conf

# Writable runtime folder (cache / logs / CA file) + permissions
RUN mkdir -p /var/www/html/runtime/cache /var/www/html/runtime/logs \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 /var/www/html/runtime

# Render sends traffic to $PORT (default 10000); the script makes Apache listen on it
RUN chmod +x /var/www/html/docker-start.sh
ENV PORT=10000
EXPOSE 10000
CMD ["/var/www/html/docker-start.sh"]
