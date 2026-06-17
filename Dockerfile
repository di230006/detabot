FROM php:8.1-apache

# The php:8.1-apache image can end up with two MPMs enabled, which makes Apache
# refuse to start (AH00534). Force the single prefork MPM that mod_php requires.
RUN a2dismod mpm_event 2>/dev/null; a2dismod mpm_worker 2>/dev/null; a2enmod mpm_prefork rewrite headers

RUN apt-get update && apt-get install -y \
    zip unzip git curl libzip-dev libpng-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN if [ -f composer.json ]; then composer install --no-interaction --optimize-autoloader --no-dev; fi

RUN mkdir -p uploads/avatars uploads/payments uploads/dentists data/sessions

RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html
RUN chmod -R 777 /var/www/html/uploads /var/www/html/data

COPY .docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/ports.conf /etc/apache2/ports.conf

# Railway injects $PORT at runtime; default to 80 for local Docker builds.
ENV PORT=80

EXPOSE 80
CMD ["apache2-foreground"]
