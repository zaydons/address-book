# Production image of the system: Apache and PHP with the code built in, configured with environment variables.
# Used for deployments such as a TrueNAS custom app - see the README. For development, use docker-compose.yml instead.
FROM php:8.5-apache

# MySQL support, and PHP's recommended production settings (errors are logged rather than shown on pages)
RUN docker-php-ext-install pdo_mysql \
	&& cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
	&& printf 'expose_php = Off\n' > "$PHP_INI_DIR/conf.d/address-book.ini"

# Serve the html/ directory, and don't reveal the Apache version (named zz- so it loads after, and overrides, Debian's security.conf)
ENV APACHE_DOCUMENT_ROOT=/var/www/address-book/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
	&& printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/zz-address-book.conf \
	&& a2enconf zz-address-book

# The code, read-only to the web server user
COPY html /var/www/address-book/html
COPY includes /var/www/address-book/includes

# Check that the web server is responding, using a static file so that health checks aren't recorded in the system's logs
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
	CMD php -r 'exit(@file_get_contents("http://localhost/css/main.css") === false ? 1 : 0);'

LABEL org.opencontainers.image.title="Address Book" \
	org.opencontainers.image.description="Simple web-based address book and contact manager" \
	org.opencontainers.image.source="https://github.com/zaydons/address-book" \
	org.opencontainers.image.licenses="MIT"
