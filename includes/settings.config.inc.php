<?php
	
	// Settings are read from includes/settings.local.inc.php if it exists, and any that it doesn't set are read from environment variables
	// Environment variables allow the system to be configured without editing files, such as when running in a container (see the README)
	if(file_exists(__DIR__ . "/settings.local.inc.php")) {
		require_once(__DIR__ . "/settings.local.inc.php");
	};
	
	// Settings which can be set as environment variables
	foreach(array('DB_PATH', 'SITE_URL', 'TIMEZONE', 'LOGIN_MAX_FAILED_USERNAME', 'LOGIN_MAX_FAILED_IP', 'LOGIN_LOCKOUT_MINUTES') as $setting_name) {
		if(!defined($setting_name) && getenv($setting_name) !== false) {
			define($setting_name, getenv($setting_name));
		};
	};
	
	// Check that settings have been provided in one of the two ways
	if(!defined('SITE_URL') && !defined('TIMEZONE')) {
		// Output to screen that the settings are missing and go no further
		echo 'The system has not been configured. Please create a file inside the includes/ directory called "settings.local.inc.php", or set the settings as environment variables.';
		echo '<br>';
		echo 'For an example file simply create a copy of the "EXAMPLE.settings.local.inc.php" and rename it to "settings.local.inc.php", you can then input the details relating to your set up.';
		die();
	};
	
	// Check that the required settings for the system to function have been defined
	// Initialise an $errors array to store any errors
	$errors = array();
	
	// Check that constants are defined
	if(!defined('SITE_URL')) 	{ $errors[] = "SITE_URL is not defined. Please add the following as a new line to your includes/settings.local.inc.php file: <b>define('SITE_URL', 'YOUR SITE URL');</b>"; };
	if(!defined('TIMEZONE')) 	{ $errors[] = "TIMEZONE is not defined. Please add the following as a new line to your includes/settings.local.inc.php file: <b>define('TIMEZONE', 'YOUR TIMEZONE');</b>"; };

	// Output the errors to screen if any are present
	if(!empty($errors)) {
		echo '<p>There appear to be some issues with your configuration. Please review the following errors:</p>';
		echo '<ul>';
		foreach($errors as $error) {
			echo '- '. $error . '<br>';
		};
		echo '</ul>';
		echo '<p>Please remember to include all of the example line but replacing the key information with that which relates to your system. Also ensure that the settings.local.inc.php file starts with the first line with <b>' . htmlspecialchars("<?php ") . '</b>. Without this then any setting you set won\'t work!</p>';
		die();
	}; // Close if(!empty($errors))
	
	// The SQLite database file, which is created if it doesn't exist
	// By default this is in the data/ directory, which is outside of the html/ directory so that it can't be downloaded
	defined("DB_PATH")								?	null	:	define("DB_PATH", dirname(__DIR__) . "/data/address-book.sqlite");

	// Limits on failed logins, to slow down attempts to guess passwords
	// These can be overridden in settings.local.inc.php
	// Number of failed logins allowed for a single username within the window before further attempts are blocked
	defined("LOGIN_MAX_FAILED_USERNAME")			?	null	:	define("LOGIN_MAX_FAILED_USERNAME", 5);
	// Number of failed logins allowed from a single IP address within the window before further attempts are blocked
	defined("LOGIN_MAX_FAILED_IP")					?	null	:	define("LOGIN_MAX_FAILED_IP", 20);
	// Length of the window, in minutes
	defined("LOGIN_LOCKOUT_MINUTES")				?	null	:	define("LOGIN_LOCKOUT_MINUTES", 15);

	// Set page names
	defined("PAGENAME_INDEX")						?	null	:	define("PAGENAME_INDEX", "Address Book");
	defined("PAGENAME_LOGIN")						?	null	:	define("PAGENAME_LOGIN", "Log In");
	defined("PAGENAME_LOGOUT")						?	null	:	define("PAGENAME_LOGOUT", "Log Out");
	defined("PAGENAME_USERS")						?	null	:	define("PAGENAME_USERS", "Users");
	defined("PAGENAME_USERSADD")					?	null	:	define("PAGENAME_USERSADD", "Add User");
	defined("PAGENAME_USERSDELETE")					?	null	:	define("PAGENAME_USERSDELETE", "Delete User");
	defined("PAGENAME_USERSUPDATE")					?	null	:	define("PAGENAME_USERSUPDATE", "Update User");
	defined("PAGENAME_LOGS")						?	null	:	define("PAGENAME_LOGS", "Logs");
	defined("PAGENAME_CONTACTS")					?	null	:	define("PAGENAME_CONTACTS", "Contacts");
	defined("PAGENAME_CONTACTSADD")					?	null	:	define("PAGENAME_CONTACTSADD", "Add Contact");
	defined("PAGENAME_CONTACTSDELETE")				?	null	:	define("PAGENAME_CONTACTSDELETE", "Delete Contact");
	defined("PAGENAME_CONTACTSUPDATE")				?	null	:	define("PAGENAME_CONTACTSUPDATE", "Update Contact");
	defined("PAGENAME_CONTACTSVIEW")				?	null	:	define("PAGENAME_CONTACTSVIEW", "View Contact");
	defined("PAGENAME_API")							?	null	:	define("PAGENAME_API", "API");
	defined("PAGENAME_APIADD")						?	null	:	define("PAGENAME_APIADD", "Add API Token");
	defined("PAGENAME_APIDELETE")					?	null	:	define("PAGENAME_APIDELETE", "Delete API Token");
	defined("PAGENAME_APIUPDATE")					?	null	:	define("PAGENAME_APIUPDATE", "Update API Token");
	defined("PAGENAME_CHANGEPASSWORD")				?	null	:	define("PAGENAME_CHANGEPASSWORD", "Change Password");
	
	// Set page links
	defined("PAGELINK_INDEX")						?	null	:	define("PAGELINK_INDEX", "index.php");
	defined("PAGELINK_LOGIN")						?	null	:	define("PAGELINK_LOGIN", "login.php");
	defined("PAGELINK_LOGOUT")						?	null	:	define("PAGELINK_LOGOUT", "logout.php");
	defined("PAGELINK_USERS")						?	null	:	define("PAGELINK_USERS", "users.php");
	defined("PAGELINK_USERSDELETE")					?	null	:	define("PAGELINK_USERSDELETE", "delete-user.php");
	defined("PAGELINK_USERSUPDATE")					?	null	:	define("PAGELINK_USERSUPDATE", "update-user.php");
	defined("PAGELINK_LOGS")						?	null	:	define("PAGELINK_LOGS", "logs.php");
	defined("PAGELINK_CONTACTSADD")					?	null	:	define("PAGELINK_CONTACTSADD", "add-contact.php");
	defined("PAGELINK_CONTACTSDELETE")				?	null	:	define("PAGELINK_CONTACTSDELETE", "delete-contact.php");
	defined("PAGELINK_CONTACTSUPDATE")				?	null	:	define("PAGELINK_CONTACTSUPDATE", "update-contact.php");
	defined("PAGELINK_CONTACTSVIEW")				?	null	:	define("PAGELINK_CONTACTSVIEW", "view-contact.php");
	defined("PAGELINK_API")							?	null	:	define("PAGELINK_API", "api.php");
	defined("PAGELINK_APIADD")						?	null	:	define("PAGELINK_APIADD", "add-api.php");
	defined("PAGELINK_APIDELETE")					?	null	:	define("PAGELINK_APIDELETE", "delete-api.php");
	defined("PAGELINK_APIUPDATE")					?	null	:	define("PAGELINK_APIUPDATE", "update-api.php");
	defined("PAGELINK_CHANGEPASSWORD")				?	null	:	define("PAGELINK_CHANGEPASSWORD", "change-password.php");
	
	// Server time zone
 	date_default_timezone_set(TIMEZONE);
	
	// Autoload classes so that they are called as and when they are required
	spl_autoload_register(function($class_name) { 
		$class_name = strtolower($class_name);
		include('class.' . $class_name . '.inc.php');
	});
	
	// Handle any unexpected errors, such as a database failure, without revealing details to the user
	set_exception_handler(function($exception) {
		// Record the full details in the server error log for the administrator
		error_log('Address Book: ' . $exception);
		// Show a generic message to the user
		if(!headers_sent()) {
			http_response_code(500);
		}
		echo 'An unexpected error occurred. Please try again later, and if the problem continues contact a system administrator.';
		exit;
	});
	
	// Security headers sent with every page
	// Stop the system being loaded inside a frame on another site (clickjacking)
	header("X-Frame-Options: DENY");
	header("Content-Security-Policy: frame-ancestors 'none'");
	// Stop browsers guessing a different content type to the one sent
	header("X-Content-Type-Options: nosniff");
	// Only send the full URL as a referrer to this site
	header("Referrer-Policy: same-origin");
	
	// Site functions
	require_once("functions.inc.php");
	
	// Notifications, for things such as error messages and success alerts
	require_once("alerts.notification.inc.php");
	
	// Validation messages for form fields, such as string lengths too long, or required fields missing
	require_once("alerts.validation.inc.php");
	
	// Begin running the Session as items in constructor are required for the system to function correctly
	$session = new Session();
	
	// Begin a new User instance as will automatically check details of the user if they are logged in etc
	$user = new User();
	
	// A user who has been given a password by someone else (including the default admin account) must change it before using the system
	if($user->authenticated && !empty($user->details['must_change_password'])) {
		// Allow the change password and log out pages, redirect anything else to the change password page
		if(!in_array(basename($_SERVER['SCRIPT_NAME']), array(PAGELINK_CHANGEPASSWORD, PAGELINK_LOGOUT))) {
			Redirect::to(PAGELINK_CHANGEPASSWORD);
		}
	}

?>