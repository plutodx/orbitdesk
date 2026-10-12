# OrbitDesk - PHP + MySQL job portal, containerized for web hosting (Railway/Render).
FROM php:8.2-apache

# MySQL driver for PDO (the app uses PDO prepared statements).
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# Copy the application into Apache's web root. index.php, login.php, etc.
# sit at the domain root, so the app's relative paths (db/request.php,
# assets/js/va.js) all resolve correctly.
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Railway/Render assign a port via $PORT. Apache listens on 80 by default,
# so at container start we rewrite it to whatever the host provides.
CMD bash -c 'PORT="${PORT:-80}"; \
    sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf; \
    sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf; \
    apache2-foreground'
