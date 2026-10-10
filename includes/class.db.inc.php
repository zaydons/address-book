<?php
	// This class is for returning an instance of the DB with a getInstance() method call
	class DB {

		// Hold any instantiated PDO object for DB in an $instance as part of a singleton call
		// Set default to null
		protected static $instance = null;
		
		// The version of the database structure which this version of the system uses. It is stored in the database
		// (PRAGMA user_version), and older databases are upgraded by the steps in upgrade() when they are opened
		const SCHEMA_VERSION = 2;

		protected function __construct() {}

		// Database static method for obtaining Singleton PDO call
		public static function get_instance() {
			// If $instance hasn't been set
			if(empty(self::$instance)) {
				// Attempt to open the SQLite database file, which is created if it doesn't exist
				try {
					// Set $instance to a new PDO, as currently not set
					// Database errors throw exceptions rather than failing silently
					self::$instance = new PDO("sqlite:" . DB_PATH, null, null, array(
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
					));
					// Wait for up to 5 seconds if another request is writing, rather than failing straight away
					self::$instance->exec('PRAGMA busy_timeout = 5000');
					// Write-ahead logging lets pages be read while another request is writing, such as when logging a page view
					self::$instance->exec('PRAGMA journal_mode = WAL');
					// Create the tables if this is a new database, or upgrade an older one
					self::create_tables(self::$instance);
					self::upgrade(self::$instance);
				} catch(PDOException $error) {
					// Record the details in the server error log, rather than displaying them, as they include the file location
					error_log('Address Book: unable to open the database at ' . DB_PATH . ' - check that the directory exists and that the web server can write to it: ' . $error->getMessage());
					// Show a generic message and go no further, as the system can't function without a database
					if(!headers_sent()) {
						http_response_code(500);
					}
					echo 'Unable to open the database. Please contact a system administrator.';
					exit;
				}
			}
			// Return the Singleton $instance
			return self::$instance;
		}

		// Create the tables from sql/schema.sql if the database is new
		private static function create_tables($db) {
			// The users table is checked for, as every database which has been set up will have one
			$exists = $db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
			if(!$exists) {
				// Set up the database in a single transaction, so that two requests at the same time can't both set it up
				$db->exec('BEGIN IMMEDIATE');
				try {
					$db->exec(file_get_contents(__DIR__ . '/../sql/schema.sql'));
					// sql/schema.sql is always the latest structure, so a new database needs no upgrades
					$db->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
					$db->exec('COMMIT');
				} catch(PDOException $error) {
					$db->exec('ROLLBACK');
					throw $error;
				}
			}
		}

		// Upgrade a database created by an older version of the system to the current structure
		// Version 0 or 1 is a database from version 1.1.0
		private static function upgrade($db) {
			if((int) $db->query('PRAGMA user_version')->fetchColumn() >= self::SCHEMA_VERSION) {
				return;
			}
			// Upgrade in a single transaction, checking the version again in case another request has just upgraded it
			$db->exec('BEGIN IMMEDIATE');
			try {
				$version = (int) $db->query('PRAGMA user_version')->fetchColumn();
				
				if($version < 2) {
					// Speeds up removing old log entries
					$db->exec('CREATE INDEX IF NOT EXISTS logs_datetime ON logs (datetime)');
					// Phone numbers are now stored as digits only, with a + at the start for international numbers
					$update = $db->prepare('UPDATE contacts SET contact_number_home = :home, contact_number_mobile = :mobile WHERE contact_id = :id');
					foreach($db->query('SELECT contact_id, contact_number_home, contact_number_mobile FROM contacts')->fetchAll(PDO::FETCH_ASSOC) as $row) {
						$update->execute(array(
							':home' => Contact::normalize_phone($row['contact_number_home']),
							':mobile' => Contact::normalize_phone($row['contact_number_mobile']),
							':id' => $row['contact_id'],
						));
					}
				}
				
				$db->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
				$db->exec('COMMIT');
			} catch(Exception $error) {
				$db->exec('ROLLBACK');
				throw $error;
			}
		}
		
	}; // Close class DB

// EOF
