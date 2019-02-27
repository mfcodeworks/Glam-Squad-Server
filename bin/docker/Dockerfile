FROM nygmarose/glamsquad

RUN docker-php-ext-install mysqli gettext hash json mbstring opcache session \
    && pecl install redis \
    && pecl install gearman \
    && docker-php-ext-enable redis gearman

RUN apt install gearman gearman-tools

RUN a2enmod rewrite
RUN a2enmod gearman

COPY ./php/php.ini /usr/local/etc/php/
COPY ./apache/000-default.conf /etc/apache2/sites-available
COPY ./apache/000-default-le-ssl.conf /etc/apache2/sites-available

RUN chown -R www-data:www-data /srv