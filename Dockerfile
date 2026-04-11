# Dev/test image for the yammi-jobs-monitoring-laravel package.
#
# Pinned to PHP 8.1 on purpose: it is the minimum supported version, so the
# test suite runs on the floor and catches features that work on newer PHP
# but not on the version we promise to support.
#
# This file is NOT shipped to end users — it is excluded from the published
# package by `.gitattributes` (`export-ignore`).
FROM php:8.1-cli

ENV DEBIAN_FRONTEND=noninteractive

# System libraries needed to build the PHP extensions below.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libonig-dev \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions:
#   mbstring  — Laravel string helpers
#   intl      — Laravel localization / Carbon
#   bcmath    — arbitrary-precision math (Laravel deps)
#   pcntl     — queue worker signal handling
#   zip       — composer / package archives
#
# Note: dom, xml, simplexml, pdo_sqlite, json, openssl, etc. are already
# bundled in the official php:8.1-cli image, so PHPUnit and the in-memory
# SQLite test database work out of the box.
RUN docker-php-ext-install -j"$(nproc)" \
        mbstring \
        intl \
        bcmath \
        pcntl \
        zip

# Composer 2 from the official image — avoids manually verifying installer
# signatures and keeps the layer small.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /app
