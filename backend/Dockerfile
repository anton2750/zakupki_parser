FROM yiisoftware/yii2-php:8.2-fpm-24.3.0

ENV TIME_ZONE Europe/Moscow
ENV PHP_ENABLE_XDEBUG 1

RUN apt-get update && apt-get -y install git wget libpq-dev
RUN docker-php-ext-install pcntl sockets pdo pdo_pgsql pgsql

WORKDIR /app

# php excimer
RUN  pecl install excimer && docker-php-ext-enable excimer
# php gmp
RUN apt-get install -y libgmp-dev && docker-php-ext-install gmp
# php imap
RUN apt-get install -y libc-client-dev libkrb5-dev  \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl  \
    && docker-php-ext-install imap

COPY ./docker/php/php.ini /usr/local/etc/php/php.ini
ADD ./docker/php/output.conf /usr/local/etc/php/conf.d/output.conf
ADD ./docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
ADD ./docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

RUN rm /usr/local/etc/php/conf.d/base.ini -f

RUN echo "Install Composer"
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version
