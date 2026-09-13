# TornadoCMS API — Laravel 13. Contenedor único nginx + php-fpm (supervisord):
# expone HTTP en el 80 para que el front (u otro servicio) le haga proxy.

FROM composer:2 AS composer-stage
FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache icu-dev libzip-dev zip oniguruma-dev mysql-client supervisor nginx \
    && docker-php-ext-install pdo_mysql bcmath intl zip

# GD para las miniaturas de la biblioteca de medios.
RUN apk add --no-cache libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd

COPY --from=composer-stage /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS deps
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

FROM base AS production
ENV APP_ENV=production
COPY api/ .
COPY docker/api/entrypoint.sh /usr/local/bin/tcms-entrypoint
COPY --from=deps /var/www/html/vendor ./vendor

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/tcms-entrypoint

# Config de nginx (docroot public/) y PHP-FPM por socket unix.
COPY docker/api/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/api/supervisord.conf /etc/supervisord.conf

# OPCACHE: rendimiento en producción (config recomendada para Laravel).
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=192'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.jit=tracing'; \
    echo 'opcache.jit_buffer_size=64M'; \
    echo 'realpath_cache_size=4096K'; \
    echo 'realpath_cache_ttl=600'; \
  } > /usr/local/etc/php/conf.d/opcache-tcms.ini

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/tcms-entrypoint"]
# El rol "app" del entrypoint lanza supervisord (nginx + php-fpm).
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
