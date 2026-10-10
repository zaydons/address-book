<?php
	/**
	 * Each of these settings can also be set as an environment variable with the same name, such as when running in a container.
	 * A value set in this file takes priority over an environment variable.
	 */

	/**
	 * The SQLite database file where the data is stored. It is created, with the default admin user, if it doesn't exist.
	 * The web server needs permission to write to the directory which contains it.
	 * Keep it outside of the html/ directory so that it can't be downloaded. Leave this commented out to use the default,
	 * which is data/address-book.sqlite in the directory above html/.
	 */
	// defined('DB_PATH')			?	null	:	define('DB_PATH', '/var/lib/address-book/address-book.sqlite');

	/**
	 * The address which is used to access this system.
	 */
	defined('SITE_URL')			?	null	:	define('SITE_URL', 'http://localhost/');

	/**
	 * The timezone to be used by the system.
	 * See https://www.php.net/manual/en/timezones.php for a list of valid timezones.
	 */
	defined("TIMEZONE")			?	null	:	define("TIMEZONE", "UTC");

	/**
	 * Optional settings, shown with their default values. Remove the // at the start of a line to change one.
	 * See "Local Settings Configuration Values" in the README for what each one does.
	 */
	// defined("PHONE_FORMAT")				?	null	:	define("PHONE_FORMAT", "us"); // "us", "uk" or "none"
	// defined("SESSION_LIFETIME_DAYS")		?	null	:	define("SESSION_LIFETIME_DAYS", 365);
	// defined("LOG_RETENTION_DAYS")		?	null	:	define("LOG_RETENTION_DAYS", 90);
	// defined("LOGIN_MAX_FAILED_USERNAME")	?	null	:	define("LOGIN_MAX_FAILED_USERNAME", 5);
	// defined("LOGIN_MAX_FAILED_IP")		?	null	:	define("LOGIN_MAX_FAILED_IP", 20);
	// defined("LOGIN_LOCKOUT_MINUTES")		?	null	:	define("LOGIN_LOCKOUT_MINUTES", 15);
