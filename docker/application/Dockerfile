#
# Development environment: PHP CLI with composer, for tests and static analysis.
#
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    vim \
    libzip-dev \
    iproute2 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# zip speeds up composer, xdebug is installed but not enabled, pcov is enabled for fast coverage
RUN docker-php-ext-install -j$(nproc) zip \
    && pecl install xdebug pcov \
    && docker-php-ext-enable pcov

RUN echo 'alias ll="ls -la"' >> /root/.bashrc \
    && echo 'alias ll="ls -la"' >> /var/www/.bashrc

COPY --chmod=755 docker/application/entrypoint /usr/local/bin/entrypoint
COPY docker/application/php-config.ini /usr/local/etc/php/conf.d/php-config.ini
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["sleep", "infinity"]
