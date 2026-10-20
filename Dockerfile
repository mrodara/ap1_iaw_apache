FROM php:8.4-apache

RUN docker-php-ext-install mysqli

# Copiar los ficheros fuente de mi web
COPY sites/static/ /var/www/static/
COPY sites/dynamic/ /var/www/dynamic/

# Copiar los ficheros de configuración de los Hosts Virtuales
COPY apache/001-static.conf /etc/apache2/sites-available/001-static.conf
COPY apache/002-dynamic.conf /etc/apache2/sites-available/002-dynamic.conf

# Asignación de permisos y activación de sitios
RUN chmod -R a+rX /var/www/static/ /var/www/dynamic/ \
    && a2dissite 000-default \
    && a2ensite 001-static.conf 002-dynamic.conf \
    && apache2ctl configtest

EXPOSE 80

CMD ["apache2-foreground"]