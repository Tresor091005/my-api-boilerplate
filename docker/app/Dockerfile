FROM serversideup/php:8.4-frankenphp AS base

USER root
RUN install-php-extensions intl bcmath

USER www-data
WORKDIR /var/www/html


FROM base AS runtime

COPY --chown=www-data:www-data . .

RUN mkdir -p \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader

COPY --chmod=755 docker/app/entrypoint.runtime.sh /etc/entrypoint.d/10-app-runtime.sh


# Keep development last until the Compose files select build targets explicitly.
FROM base AS development

USER root

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pcov \
    && docker-php-serversideup-set-id www-data "${USER_ID}:${GROUP_ID}" \
    && docker-php-serversideup-set-file-permissions --owner "${USER_ID}:${GROUP_ID}"

USER www-data

COPY --chmod=755 docker/app/entrypoint.development.sh /etc/entrypoint.d/10-app-development.sh
