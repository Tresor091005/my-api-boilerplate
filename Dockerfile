FROM serversideup/php:8.4-frankenphp

USER root

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    && docker-php-ext-install intl bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /data/caddy /config/caddy && \
    chown -R 1000:1000 /data/caddy /config/caddy && \
    chmod -R 775 /data/caddy /config/caddy

RUN docker-php-serversideup-set-id www-data 1000:1000

WORKDIR /var/www/html
COPY --chown=1000:1000 . .

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html

COPY --chown=www-data:www-data entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

RUN echo "alias a='php artisan'" >> ~/.bashrc

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]