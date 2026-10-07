<?php
	/**
	 * Each of these settings can also be set as an environment variable with the same name, such as when running in a container.
	 * A value set in this file takes priority over an environment variable.
	 */

	/**
	 * The IP/hostname of the MySQL/MariaDB server.
	 * If you are using a Docker environment then this should be the name of your Docker MySQL/MariaDB container.
	 * Otherwise this should be the address of your database server, typically this is 127.0.0.1.
	 */
	defined('DB_SERVER')    	?	null	:	define('DB_SERVER', 'mysql'); // Docker example
	// defined('DB_SERVER')    	?	null	:	define('DB_SERVER', '127.0.0.1'); // Standalone database example

	/**
	 * The username of the account which has access to the database on the MySQL/MariaDB server.
	 * If you are using Docker then this is 'address_book', the user created by docker-compose.yml.
	 * Avoid using 'root' - the user only needs SELECT, INSERT, UPDATE and DELETE on the address_book database.
	 */
	defined('DB_USER')			?	null	:	define('DB_USER', 'address_book');

	/**
	 * The password (if any) associated with the DB_USER account.
	 * If you are using Docker then this is the DB_PASS value which you set in the .env file.
	 */
	defined('DB_PASS')			?	null	:	define('DB_PASS', '');

	/**
	 * The database where the data will be stored.
	 * If you have used the default installation with the sql.sql file and didn't change any settings then this will be 'address_book'.
	 * Please note that the user set for DB_USER will need to have permission to this database.
	 */
	defined('DB_NAME')			?	null	:	define('DB_NAME', 'address_book');

	/**
	 * The address which is used to access this system.
	 */
	defined('SITE_URL')			?	null	:	define('SITE_URL', 'http://localhost/');

	/**
	 * The timezone to be used by the system.
	 * See https://www.php.net/manual/en/timezones.php for a list of valid timezones.
	 */
	defined("TIMEZONE")			?	null	:	define("TIMEZONE", "UTC");