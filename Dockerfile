FROM scratch AS volume-image
ADD . /

FROM alpine:3.24 AS base-image
RUN set -xe \
    && apk update \
    && apk add \
        bash \
        curl \
        ghostscript-fonts \
        imagemagick \
	    imagemagick-libs \
	    imagemagick-jpeg \
	    imagemagick-svg \
        libstdc++ \
        nginx \
        php85 \
        php85-common \
        php85-curl \
        php85-dom \
        php85-fileinfo \
        php85-fpm \
        php85-iconv \
        php85-mbstring \
        php85-openssl \
        php85-pecl-igbinary \
        php85-pecl-imagick \
        php85-pecl-redis \
        php85-pecl-xdebug \
        php85-simplexml \
        php85-xml \
        php85-zip \
        supervisor

FROM alpine:3.24 AS composer-image
COPY --from=volume-image /etc/composer.json /opt/composer.json
COPY --from=volume-image /etc/composer.lock /opt/composer.lock
RUN set -xe \
    && apk add --no-cache \
        bash \
        curl \
        openblas \
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
    && chmod +x /opt/composer \ 
    && /opt/composer install --no-dev --prefer-dist --optimize-autoloader

FROM base-image AS runtime-image
COPY --from=composer-image /opt/vendor/ /opt/vendor/
COPY --from=volume-image /etc/ /opt/etc/
COPY --from=volume-image /opt/public/ /opt/public/
COPY --from=volume-image /opt/app/ /opt/app/
COPY --from=volume-image /etc/php85/conf.d/50_xdebug.ini /etc/php85/conf.d/50_xdebug.ini
RUN set -xe \
    && addgroup -g 1000 -S yujiro \
    && adduser -u 1000 -D -G yujiro yujiro \
    && mkdir -p /var/log/php85 \
    && chown -R yujiro /var/log/php85 \
    && mkdir -p /var/lib/php85 \
    && chgrp -R yujiro /var/lib/php85 \
    && mkdir -p /var/run/php-fpm85 \
    && chown -R yujiro:yujiro /var/run/php-fpm85 \
    && mkdir -p /var/tmp/nginx \
    && chmod 755 /var/tmp/nginx \
    && mkdir -p /var/lib/nginx/tmp/fastcgi \
    && chmod 755 -R /var/lib/nginx \
    && touch /var/log/xdebug.log \
    && chown -R yujiro:yujiro /var/log/xdebug.log
CMD ["supervisord", "-c", "/opt/etc/supervisord.conf"]
