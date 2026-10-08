<?php
	/**
	 * Copies the data from an Address Book MySQL/MariaDB database (version 1.0.x) into a new SQLite database file.
	 * Run from the command line, for example:
	 *
	 *   php tools/mysql-to-sqlite.php --host=127.0.0.1 --user=root --password=secret --database=address_book --output=data/address-book.sqlite
	 *
	 * The output file must not already exist. Passwords, API tokens, users, contacts and logs are all copied unchanged.
	 * If the default admin account still has the default password, it will be asked to choose a new one at next login.
	 *
	 * If accented characters (such as é or ë) look wrong after copying, such as "Ã©" instead of "é", the system was
	 * originally connecting to MySQL as Latin-1. Delete the new file and run this again with --charset=latin1.
	 */

	if(PHP_SAPI !== 'cli') {
		exit("This script must be run from the command line.\n");
	}

	// Read the options
	$options = getopt('', array('host:', 'port:', 'user:', 'password::', 'database:', 'output:', 'charset:'));
	foreach(array('host', 'user', 'database', 'output') as $required) {
		if(empty($options[$required])) {
			fwrite(STDERR, "Usage: php tools/mysql-to-sqlite.php --host=HOST [--port=3306] --user=USER [--password=PASSWORD] --database=address_book --output=PATH/address-book.sqlite [--charset=utf8mb4|latin1]\n");
			exit(1);
		}
	}
	if(!extension_loaded('pdo_mysql')) {
		fwrite(STDERR, "The pdo_mysql PHP extension is needed to read the MySQL database.\n");
		exit(1);
	}
	if(file_exists($options['output'])) {
		fwrite(STDERR, "The output file " . $options['output'] . " already exists. Choose a new file, so that no data is overwritten.\n");
		exit(1);
	}

	// The default admin account's original password hash (LetMeIn123)
	const DEFAULT_ADMIN_HASH = '$2y$10$Mjg4OGQ1NzdmNWY2ZGJiO.5O1IjWagPSmROXjw9h1IWz3JYyr5Iu.';

	try {
		// Connect to MySQL
		$port = isset($options['port']) ? (int) $options['port'] : 3306;
		// The connection character set must match the one the system used when it saved the data
		$charset = $options['charset'] ?? 'utf8mb4';
		if(!in_array($charset, array('utf8mb4', 'utf8', 'latin1'), true)) {
			fwrite(STDERR, "--charset must be utf8mb4, utf8 or latin1.\n");
			exit(1);
		}
		$mysql = new PDO('mysql:host=' . $options['host'] . ';port=' . $port . ';dbname=' . $options['database'] . ';charset=' . $charset, $options['user'], $options['password'] ?? '', array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		));

		// Create the SQLite database with the same structure the system creates
		$sqlite = new PDO('sqlite:' . $options['output'], null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$sqlite->exec('PRAGMA journal_mode = WAL');
		$sqlite->exec('BEGIN');
		$sqlite->exec(file_get_contents(__DIR__ . '/../sql/sql.sql'));
		// Remove the default admin user added by the structure, as the users are copied from MySQL
		$sqlite->exec('DELETE FROM users');

		// The columns to copy from each table
		$tables = array(
			'api' => array('api_id', 'ip', 'cosmetic_name'),
			'contacts' => array('contact_id', 'first_name', 'middle_name', 'last_name', 'contact_number_home', 'contact_number_mobile', 'contact_email', 'date_of_birth', 'address_line_1', 'address_line_2', 'address_town', 'address_county', 'address_post_code'),
			'logs' => array('log_id', 'datetime', 'action', 'url', 'user', 'ip', 'user_agent'),
			'users' => array('user_id', 'username', 'hashed_password', 'full_name', 'must_change_password'),
		);

		foreach($tables as $table => $columns) {
			// Only copy columns which exist in MySQL, as older versions don't have must_change_password
			$existing = $mysql->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
			$copy = array_values(array_intersect($columns, $existing));

			$insert = $sqlite->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $copy) . ') VALUES (:' . implode(', :', $copy) . ')');
			$rows = $mysql->query('SELECT `' . implode('`, `', $copy) . '` FROM `' . $table . '`');
			$count = 0;
			foreach($rows as $row) {
				$insert->execute($row);
				$count++;
			}
			echo str_pad($table, 10) . $count . " rows copied\n";
		}

		// Make sure the default admin account has to change its password if it still has the default one
		$flagged = $sqlite->exec("UPDATE users SET must_change_password = 1 WHERE hashed_password = '" . DEFAULT_ADMIN_HASH . "'");
		if($flagged) {
			echo "The default admin account still has the default password, so it will be asked to choose a new one at next login.\n";
		}

		$sqlite->exec('COMMIT');
		echo "Done. The data is in " . $options['output'] . "\n";
	} catch(PDOException $error) {
		fwrite(STDERR, "Error: " . $error->getMessage() . "\n");
		// Remove the partly-written output file
		$sqlite = null;
		foreach(array('', '-wal', '-shm') as $suffix) {
			if(isset($options['output']) && file_exists($options['output'] . $suffix)) {
				unlink($options['output'] . $suffix);
			}
		}
		exit(1);
	}
