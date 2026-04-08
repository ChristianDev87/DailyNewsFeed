# Stage 1: Composer Dependencies
FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Stage 2: Runtime
FROM php:8.2-fpm-alpine
RUN apk add --no-cache nginx supervisor \
    && docker-php-ext-install pdo_mysql \
    && echo "[www]" > /usr/local/etc/php-fpm.d/zzz-env.conf \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zzz-env.conf

COPY nginx.conf /etc/nginx/nginx.conf
COPY supervisord.conf /etc/supervisord.conf
COPY --from=deps /app/vendor /var/www/html/vendor
COPY src/ /var/www/html/src/
COPY public/ /var/www/html/public/
COPY templates/ /var/www/html/templates/

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
