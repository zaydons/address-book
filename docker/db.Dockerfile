# Database image for production deployments (such as a TrueNAS custom app), with the database set-up built in.
# On first start with an empty data directory, MySQL creates the address_book database and tables, and gives the
# MYSQL_USER user (which must be address_book) access to read and change data only.
FROM mysql:9

COPY sql/sql.sql /docker-entrypoint-initdb.d/01-initialise.sql
COPY docker/mysql-app-user.sql /docker-entrypoint-initdb.d/02-app-user.sql

LABEL org.opencontainers.image.title="Address Book database" \
	org.opencontainers.image.source="https://github.com/zaydons/address-book" \
	org.opencontainers.image.licenses="MIT"
