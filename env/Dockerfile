FROM php:7.4-fpm-alpine

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories && \
    apk add --no-cache  \
    php-dom \
    php-xml \
    php-xmlwriter \
    php-xmlreader \
    php-tokenizer  \
    composer &&  \
    composer self-update && \
    curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s  \
    sockets pcntl zip event ev ffi xdebug opcache

VOLUME /var/www
WORKDIR /var/www