<?php
	class User {
		// User class used to manipulate users and to check if user is logged in/authenticated
		
		private $db = null; // Used to store an instance of the database
		
		public 	$authenticated = false, // Used to know if the user is authenticated or not
				$details = false, // Used to store all details about the user in an array
				$name = false, // The users name as retrieved from the database
				$username = false; // The users username as retrieved from teh database
				
		// Constructor
		public function __construct() {
			// Obtain an instance of the database
			$this->db = DB::get_instance();
			// Check that the user has been authenticated
			$this->is_authenticated();
			// Check that the users security checked settings haven't changed
			$this->check_security();
		}
		
		// Used to check if the user has been authenticated
		public function is_authenticated() {
			// Pull in global $session
			global $session;
			// Check if user is authenticated
			if($session->get('authenticated_user') == 1) {
				
				// Check if user ID has been submitted
				if($session->get('authenticated_user_id')) {
					// Lookup the user_id in the DB
					$result = $this->find_id($session->get('authenticated_user_id'));
					
					// Check if the user ID is valid in the database
					if($result) {
						// User is logged in
						$this->authenticated = true;
						
						// Pass through details of the $result in raw format to the object
						$this->details = $result;
						
						// Build the users full name for ease in future
						$this->name = $result['full_name'];
						
						// Set the instance with the users username
						$this->username = $result['username'];
					} else {
						// User does not exist in the database - remove all authentication as a fail-safe
						$this->remove_authenticated();
					}
				} else {
					// User hasn't been authenticated correctly - user_id not present, remove all authentication as a fail-safe
					$this->remove_authenticated();
				}
			} else {
				// User hasn't been authenticated, remove all authentication as a fail-safe
				$this->remove_authenticated();
			}
		}
		
		// Used to check if users details have changed at any point and to automatically log a user out if they have changed
		private function check_security() {
			// Pull in global $session
			global $session;
			
			// Check that the users IP address hasn't changed
			if(($_SERVER['REMOTE_ADDR'] != $session->get('user_ip'))) {
				// Delete the $session->get('user_ip') to avoid redirects
				$session->remove('user_ip');
				// Log the user out
				$this->logout('security_failed');
			};
				
			// Check that the users HTTP agent hasn't changed
			if((($_SERVER['HTTP_USER_AGENT'] ?? '') != $session->get('user_agent'))) {
				// Delete the $session->get('user_agent') to avoid redirects
				$session->remove('user_agent');
				// Log the user out
				$this->logout('security_failed');
			};
				
			// Check details if the user has been authenticated
			if($this->authenticated) {
				// Check that the users IP address is the same as when they logged in
				if(($_SERVER['REMOTE_ADDR'] != $session->get('authenticated_user_ip'))) {
					// Delete the $session->get('authenticated_user_ip') to avoid redirects
					$session->remove('authenticated_user_ip');
					// Log the user out
					$this->logout('security_failed');
				}
				// Check that the users HTTP agent is the same as when they logged in
				if((($_SERVER['HTTP_USER_AGENT'] ?? '') != $session->get('authenticated_user_agent'))) {
					// Delete the $session->get('authenticated_user_agent') to avoid redirects
					$session->remove('authenticated_user_agent');
					// Log the user out
					$this->logout('security_failed');
				}
			}
		}
		
		// Used to attempt a login based on a passed in username/password
		public function attempt_login($username = null, $password = null) {
			// Check if $username and $password are set
			if(isset($username) && isset($password) && !empty($username) && !empty($password)) {
				// $username and $password set
				// Lookup the $username
				$user = $this->find_username($username);
				
				// Check if username could be found
				if($user) {
					// Username found
					// Check that the password is correct
					$result = $this->password_check($password, $user['hashed_password']);
					
					// Check that the password matches
					if($result) {
						// Username and password matches
						// Upgrade the stored hash if it was made with an older algorithm or cost
						if(password_needs_rehash($user['hashed_password'], PASSWORD_DEFAULT)) {
							$this->update(array('hashed_password' => $this->password_encrypt($password)), $user['user_id']);
						}
						// Return the $user details
						return $user;
					} else {
						// Password doesn't match
						return false;
					}
				} else {
					// Username not found
					return false;
				}
			} else {
				// $username and $password not set
				return false;
			} // Close if(isset($username) && isset($password) && !empty($username) && !empty($password))
		}
		
		// Used to remove any authenticated user 
		public function remove_authenticated() {
			// Call in the $session as required
			global $session;
			
			// Remove Session values first
			$session->remove('authenticated_user');
			$session->remove('authenticated_user_ip');
			$session->remove('authenticated_user_agent');
			$session->remove('authenticated_user_time');
			$session->remove('authenticated_user_id');
			$session->remove('authenticated_user_username');
			
			// Remove any set properties in this instance
			$this->authenticated = false;
			$this->details = false;
			$this->name = false;
		}
		
		// Used to set various session values which will be used for checking that the user is logged in correctly
		// Called after a user has successfully been logged in
		public function set_logged_in($user = null) {
			// Call in the $session as required
			global $session; 
			
			// Check that the relevant information has been sent
			if(isset($user) && !empty($user)) {
				// Remove any pre-existing settings
				$this->remove_authenticated();
				
				// Issue a new session ID so that a session ID obtained before logging in can't be used to hijack the session
				session_regenerate_id(true);
				// Remove the CSRF token so that a new one is generated for the authenticated session
				$session->remove('csrf_token');
				
				// Set value to notify that the user has been authenticated
				$session->set('authenticated_user', '1');
				$this->authenticated = true;
				
				// Set details gathered about the session from when the user was authenticated
				// Remove any pre-existing details
				$session->set('authenticated_user_ip', $_SERVER['REMOTE_ADDR']);
				$session->set('authenticated_user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
				$session->set('authenticated_user_time', time());
				
				// Set details gathered about the user
				$session->set('authenticated_user_id', $user['user_id']);
				$session->set('authenticated_user_username', $user['username']);
				
				// Set user name and username
				$this->name = $user['full_name'];
				$this->username = $user['username'];
			}
		}
		
		// Used for verifying that users supplied password matches
		private function password_check($password, $existing_hash) {
			// password_verify() reads the algorithm and salt from the existing hash and compares in constant time
			return password_verify($password, $existing_hash);
		}
	
		// Method to find a specific username
		public function find_username($username = null) {
			// If the $username has been sent
			if(!empty($username)) {
				// Prepare a SQL query to search for a username from the database
				$sql = "
					SELECT * FROM users 
					WHERE username = :username
				";
				$stmt = $this->db->prepare($sql);
				
				// Pass in the $username into the prepared statement and execute
				$stmt->bindParam(':username', $username);
				
				// Execute the query
				$stmt->execute();
				
				// Fetch the results from the prepared statement
				$result = $stmt->fetch();
				
				// Check if a username could be found
				if($result) {
					// Username found, return all of the details
					return $result;
				} else {
					// Username not found, return false
					return false;
				}
			} else {
				// $username hasn't been sent
				return false;
			}
		}
		
		// Method to find a specific user by their user_id
		public function find_id($id = null) {
			// If the $id has been sent
			if(!empty($id)) {
				// Prepare a SQL query to search for a id from the database
				$sql = "
					SELECT * FROM users 
					WHERE user_id = :user_id
				";
				$stmt = $this->db->prepare($sql);
				
				// Pass in the $id into the prepared statement and execute
				$stmt->bindParam(':user_id', $id);
				
				// Execute the query
				$stmt->execute();
				
				// Fetch the results from the prepared statement
				$result = $stmt->fetch();
				
				// Check if a user could be found
				if($result) {
					// User found, return all of the details
					return $result;
				} else {
					// User not found, return false
					return false;
				}
			} else {
				// $id hasn't been sent
				return false;
			}
		}
		
		// Used to log a user out
		public function logout($message = null) {
			// Bring in the global Session
			global $session;
			// Bring in the $notification array so that standard messages are brought in
			global $notification;
			
			// Remove authenticated elements
			$this->remove_authenticated();
			
			// Issue a new session ID so that the authenticated session ID can't be reused
			session_regenerate_id(true);
			// Remove the CSRF token which belonged to the authenticated session
			$session->remove('csrf_token');
			
			// Use switch statement to determine if user has been automatically logged out due to failing a security check
			switch($message) {
				case 'not_authenticated' :
					// Log that the user isn't authenticate to view a particular page
					// Create new Log instance, and log the action to the database
					$log = new Log('not_authenticated');
					// Set session message
					$session->message_alert($notification['authenticate']['not_authenticated'], 'danger');
					break;
				case 'security_failed' :
					// Log that the user has been automatically logged out due to a security failure
					// Create new Log instance, and log the action to the database
					$log = new Log('logout_security');
					// Set session message
					$session->message_alert($notification['logout']['security_failed'], 'danger');
					break;
				default :
					// No $message has been sent, use the default logout message
					$session->message_alert($notification['logout']['success'], 'success');
			}
			
			// Redirect user
			Redirect::to(PAGELINK_LOGIN);
		}
		
		// Method to find all users in the database
		public function find_all() {
			// Return all 
			return $this->db->query('SELECT * FROM users', PDO::FETCH_ASSOC);
		}
		
		// Method to delete a particular user
		public function delete($id = null) {
			// If the $id has been sent
			if(!empty($id)) {	
				// Begin prepared statement to delete a single ID from the database
				$sql = '
					DELETE FROM users 
					WHERE user_id = :user_id
				';
				$stmt = $this->db->prepare($sql);

				// Pass in the $id into the prepared statement and execute
				$stmt->bindParam(':user_id', $id);

				// Execute the prepared statement
				$result = $stmt->execute();

				if($result) {
					// Delete was successful
					return true;
				} else {
					// Delete failed
					return false;
				}
			} else {
				// $id hasn't been sent
				return false;
			}
		}
		
		// Method to update a particular user
		public function update($values = array(), $id = null) {
			// This method works by accepting a $values array which contains the details of the fields which are to be updated
			// Check that the $values array and $id isn't empty
			if(!empty($values) && !empty($id)) {
				// Array has values, begin building the SQL query to be used to update user
				$sql = "UPDATE users SET ";
				
				// Count the number of values in the array so that a comma (,) is added after each section of the loop apart from the last one
				$i = 0;
				$c = count($values);
				
				// Cycle through each value in the array
				foreach($values as $key => $value) {
					if($i++ < $c - 1) {
						// Append to the $sql, and include a comma
						$sql .= $key . " = :" . $key . ", ";
					} else {
						// Append to the $sql, but leave off the comma
						$sql .= $key . " = :" . $key . " ";
					}
				}
				
				// Specify which user to update - the ID is unique, so only 1 record is updated
				$sql .= " WHERE user_id = :user_id";
				
				// Begin a prepared statement using the previous $sql
				$stmt = $this->db->prepare($sql);
				
				// Pass in values from the $values array to complete the prepared statement
				foreach($values as $key => &$value) {
					$stmt->bindParam(':' . $key, $value);
				}
				// Bind the user ID to the prepared statement
				$stmt->bindParam(':user_id', $id);
				
				// Execute the prepared statement
				$result = $stmt->execute();
				
				// Check if successful
				if($result) {
					// Update successful
					return true;
				} else {
					// Update failed
					return false;
				}
			} else {
				// $values array or $id was empty was empty
				return false;
			}
		}
		
		// Method to create a new user
		public function create($values = array()) {
			// This method works by accepting a $values array which contains the details of the fields which are to be inserted
			// Check that the array isn't empty
			if(!empty($values)) {
				// Obtain a DB instance
				$db = DB::get_instance();
				
				// Array has values, begin building the SQL query to be used to create user
				$sql = "INSERT INTO users (";
				
				// Add in the user_id as won't be submitted as part of the $values array
				$sql .= "user_id, ";
				
				// Count the number of values in the array so that a comma (,) is added after each section of the loop apart from the last one
				$i = 0;
				$c = count($values);
				
				// Cycle through each value in the array
				foreach($values as $key => $value) {
					if($i++ < $c - 1) {
						// Append to the $sql, and include a comma
						$sql .= $key . ", ";
					} else {
						// Append to the $sql, but leave off the comma
						$sql .= $key . " ";
					}
				}
				
				$sql .= ") VALUES (";
				
				// Add in the user_id as won't be submitted as part of the $values array
				$sql .= ":user_id, ";
				
				// Reset counters
				// Count the number of values in the array so that a comma (,) is added after each section of the loop apart from the last one
				$i = 0;
				$c = count($values);
				
				// Cycle through each value in the array, this time specifying the keys to insert as part of the prepared statement
				foreach($values as $key => $value) {
					if($i++ < $c - 1) {
						// Append to the $sql, and include a comma
						$sql .= ":" . $key . ", ";
					} else {
						// Append to the $sql, but leave off the comma
						$sql .= ":" . $key . " ";
					}
				}
				// End the $sql
				$sql .= ")";
				
				// Begin a prepared statement using the previous $sql
				$stmt = $db->prepare($sql);
				
				// Generate an ID with a length of 12
				$id = $this->generate_id(12);
				$stmt->bindParam(':user_id', $id);
				
				// Pass in values from the $values array to complete the prepared statement
				foreach($values as $key => &$value) {
					$stmt->bindParam(':' . $key, $value);
				}
				
				// Execute the prepared statement
				$result = $stmt->execute();
				
				// Check if successful
				if($result) {
					// Insert successful
					return true;
				} else {
					// Insert failed
					return false;
				}
			} else {
				// Array was empty
				return false;
			}
		}
		
		// Generate an ID to be used as the unique key associated with a new contact which is being created
		private function generate_id($token_length) {
			// Use a cryptographically secure generator so that the value can't be predicted
			return Random::string($token_length);
		}
		
		// Used to hash a password for storage in the database
		// Requires a $password to be passed in
		public function password_encrypt($password = null) {
			// Check that a password has been sent
			if(!empty($password)) {
				// Hash the $password using PHP's current recommended algorithm, which generates its own salt
				return password_hash($password, PASSWORD_DEFAULT);
			} else {
				// Password wasn't sent
				return false;
			};
		}
		
		// Used to validate a new password against the password rules, returns an array of any errors found
		public function password_errors($password, $confirm_password) {
			// Bring in the $validation array so that standard messages are brought in
			global $validation;
			
			// Initialise the $errors array where errors will be sent and then retrieved from
			$errors = array();
			
			// Password must be at least 8 characters in length
			if(strlen($password) < 8) 		{ $errors[] = $validation["too_short"]["user"]["password"]; };
			// Password must be the same as the confirmed password
			if($password !== $confirm_password) 	{ $errors[] = $validation["password"]["no_match"]; };
			
			// Password must contain at least 1 lower case character (a-z)
			if(preg_match("~[a-z]~", $password) == 0) { $errors[] = $validation["password"]["no_lowercase"]; };
			// Password must contain at least 1 upper case character (A-Z)
			if(preg_match("~[A-Z]~", $password) == 0) { $errors[] = $validation["password"]["no_uppercase"]; };
			// Password must contain at least 1 numeric character (0-9)
			if(preg_match("~[0-9]~", $password) == 0) { $errors[] = $validation["password"]["no_numeric"]; };
			
			// Return any errors found
			return $errors;
		}
		
	} // Close class User
// EOF