# Dockerfile (repo root)
FROM php:8.2-apache

# system deps
RUN apt-get update && apt-get install -y zip unzip git \
  && rm -rf /var/lib/apt/lists/*

# enable rewrite
RUN a2enmod rewrite

# set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ensure .htaccess is allowed in the public folder
RUN printf '<Directory %s>\n    AllowOverride All\n</Directory>\n' "${APACHE_DOCUMENT_ROOT}" \
    > /etc/apache2/conf-available/public-root.conf \
 && a2enconf public-root

# composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

COPY . .

RUN chown -R www-data:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} \; \
 && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
CMD ["apache2-foreground"]
