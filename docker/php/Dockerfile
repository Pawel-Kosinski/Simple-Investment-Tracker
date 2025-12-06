FROM php:8.3-fpm-alpine

ENV RUN_DEPS \
    zlib \
    libzip \
    libpng \
    libjpeg-turbo \
    postgresql-libs \
    curl

ENV BUILD_DEPS \
    zlib-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    postgresql-dev \
    curl-dev

ENV PHP_EXTENSIONS \
    opcache \
    zip \
    gd \
    bcmath \
    pgsql \
    pdo_pgsql \
    curl

RUN apk add --no-cache --virtual .build-deps $BUILD_DEPS \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j "$(nproc)" $PHP_EXTENSIONS \
    && apk del .build-deps

RUN apk add --no-cache --virtual .run-deps $RUN_DEPS

RUN echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini;
RUN echo 'upload_max_filesize = 10M' >> /usr/local/etc/php/conf.d/docker-php-uploads.ini;
RUN echo 'post_max_size = 10M' >> /usr/local/etc/php/conf.d/docker-php-uploads.ini;

COPY . /app

VOLUME ["/app"]