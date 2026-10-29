FROM alpine:3.24 AS composer-image
RUN set -xe \
    && apk update \
    && apk add \
        bash \
        curl \
        php85 \
        php85-common \
        php85-curl \
        php85-fileinfo \
        php85-iconv \
        php85-mbstring \
        php85-openssl \
        php85-phar \
        php85-simplexml \
    && cd /opt \
    && curl -LO https://github.com/composer/composer/releases/download/2.11.0/composer.phar \
    && mv composer.phar /opt/composer \
    && chmod +x /opt/composer 
CMD ["ash"]
