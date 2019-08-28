FROM php:7.3-alpine

COPY --from=composer:1.9 /usr/bin/composer /usr/bin/composer