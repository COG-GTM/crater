FROM php:8.4.27-fpm-alpine

RUN docker-php-ext-install pdo pdo_mysql bcmath

COPY docker-compose/crontab /etc/crontabs/root

CMD ["crond", "-f"]
