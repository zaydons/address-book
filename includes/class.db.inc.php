<?php
	// This class is for returning an instance of the DB with a getInstance() method call
	class DB {
		
		// Hold any instantiated PDO object for DB in an $instance as part of a singleton call
		// Set default to null
		protected static $instance = null;
		
		protected function __construct() {}
		
		// Database static method for obtaining Singleton PDO call
		public static function get_instance() {
			// If $instance hasn't been set
			if(empty(self::$instance)) {
				// Attempt to create a new PDO connection
				try {
					// Set $instance to a new PDO, as currently not set
					// Database errors throw exceptions rather than failing silently
					self::$instance = new PDO(DB_TYPE.":host=".DB_SERVER.";dbname=".DB_NAME, DB_USER, DB_PASS, array(
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
					));
				} catch(PDOException $error) {
					// Record the details in the server error log, rather than displaying them, as they may include the database host or username
					error_log('Address Book: unable to connect to the database: ' . $error->getMessage());
					// Show a generic message and go no further, as the system can't function without a database
					if(!headers_sent()) {
						http_response_code(500);
					}
					echo 'Unable to connect to the database. Please contact a system administrator.';
					exit;
				}
			}
			// Return the Singleton $instance
			return self::$instance;
		}
		
	}; // Close class DB
	
// EOF