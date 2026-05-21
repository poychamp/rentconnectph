FROM php:8.2-cli-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        gosu \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid "${GID}" app \
    && useradd --uid "${UID}" --gid app --create-home --shell /bin/bash app

WORKDIR /var/www/html

COPY docker/config/php/entrypoint.sh /usr/local/bin/rentconnectph-entrypoint
RUN chmod +x /usr/local/bin/rentconnectph-entrypoint

EXPOSE 8000

ENTRYPOINT ["rentconnectph-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
