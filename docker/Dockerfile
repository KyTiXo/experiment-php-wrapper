FROM php:8.4-cli-bookworm AS dev
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl ca-certificates libcurl4-openssl-dev unzip \
    && docker-php-ext-install pcntl curl \
    && rm -rf /var/lib/apt/lists/*
ENV BUN_INSTALL=/opt/bun BUN_VERSION=1.3.12
RUN curl -fsSL https://bun.sh/install | bash
ENV PATH="${BUN_INSTALL}/bin:${PATH}"
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*
RUN curl -L -o /usr/local/bin/php-cs-fixer https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/download/v3.95.25/php-cs-fixer.phar \
    && chmod +x /usr/local/bin/php-cs-fixer
WORKDIR /app

FROM php:8.4-fpm-bookworm AS fpm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl ca-certificates libcurl4-openssl-dev unzip \
    && docker-php-ext-install pcntl curl \
    && rm -rf /var/lib/apt/lists/*
ENV BUN_INSTALL=/opt/bun BUN_VERSION=1.3.12
RUN curl -fsSL https://bun.sh/install | bash
ENV PATH="${BUN_INSTALL}/bin:${PATH}"
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-jit.conf
WORKDIR /app
RUN mkdir -p /var/jit-state && chown www-data:www-data /var/jit-state
CMD ["php-fpm", "-F"]

FROM dev AS cli
CMD ["php", "supervisor/cli.php"]
