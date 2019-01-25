FROM nygmarose/glamsquad

RUN docker-php-ext-install mysqli gettext hash json mbstring opcache session

RUN a2enmod rewrite

COPY ./php/php.ini /usr/local/etc/php/
COPY ./apache/000-default.conf /etc/apache2/sites-available

RUN chown -R www-data:www-data /srv