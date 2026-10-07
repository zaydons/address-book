#!/bin/sh
# Starts the system. Before starting Apache, makes sure that the web server user can write to the database directory,
# such as a newly-created TrueNAS dataset owned by root. Only the directory and the database files are changed.
set -e

DB_DIR=$(dirname "${DB_PATH:-/data/address-book.sqlite}")
if [ "$(id -u)" = 0 ] && [ -d "$DB_DIR" ]; then
	chown www-data:www-data "$DB_DIR"
	for file in "$DB_PATH" "$DB_PATH-wal" "$DB_PATH-shm"; do
		if [ -e "$file" ]; then
			chown www-data:www-data "$file"
		fi
	done
fi

exec docker-php-entrypoint "$@"
