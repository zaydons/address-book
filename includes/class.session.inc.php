<?php
	class Session {
		// Constructor
		// A stateless session (such as for an API call, which authenticates with a token) isn't saved and sets no cookie
		public function __construct($stateless = false) {
			if($stateless) {
				$_SESSION = array();
				$this->obtain_user_details();
				return;
			}
			
			// Only accept session IDs which were issued by this server, and only from cookies (never the URL)
			ini_set('session.use_strict_mode', '1');
			ini_set('session.use_only_cookies', '1');
			// How long users stay logged in, in seconds - 0 means until the browser is closed
			$lifetime = max(0, (int) SESSION_LIFETIME_DAYS) * 86400;
			// Keep sessions on the server for as long as the cookie lasts (PHP's default removes them after 24 minutes without use)
			ini_set('session.gc_maxlifetime', (string) max($lifetime, 1440));
			// Keep sessions next to the database, so that restarting or updating the system doesn't log everyone out
			$session_directory = dirname(DB_PATH) . '/sessions';
			if(!is_dir($session_directory)) {
				@mkdir($session_directory, 0700, true);
			}
			if(is_dir($session_directory) && is_writable($session_directory)) {
				session_save_path($session_directory);
			}
			// Harden the session cookie
			$cookie = array(
				'lifetime' => $lifetime,
				'path' => '/',
				'secure' => $this->is_https(), // Only send the cookie over HTTPS when the site is served over HTTPS
				'httponly' => true, // Stop JavaScript reading the cookie
				'samesite' => 'Lax' // Stop the cookie being sent with requests made by other sites
			);
			session_set_cookie_params($cookie);
			// Start the session
			session_start();
			// Move the cookie's expiry forward on every visit, so that users who keep using the system stay logged in
			if($lifetime > 0 && !headers_sent()) {
				setcookie(session_name(), session_id(), array(
					'expires' => time() + $lifetime,
					'path' => $cookie['path'],
					'secure' => $cookie['secure'],
					'httponly' => $cookie['httponly'],
					'samesite' => $cookie['samesite'],
				));
			}
			// Log the users details
			$this->obtain_user_details();
		}
		
		// Used to check if the site is being accessed over HTTPS
		private function is_https() {
			// Either the web server reports HTTPS, or the configured SITE_URL is HTTPS (such as when behind a reverse proxy which handles HTTPS)
			return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || stripos(SITE_URL, 'https://') === 0;
		}
		
		// Set a session key with a value
		public function set($key, $value) {
			if(isset($_SESSION[$key]) || !empty($_SESSION[$key])) {
				// Concatenate if value is already present
				$_SESSION[$key] .= $value;
			} else {
				// Otherwise set as a new value
				$_SESSION[$key] = $value;
			}
		}
		
		// Retrieve a session key
		public function get($key) {
			if(isset($_SESSION[$key])) {
				return $_SESSION[$key];
			} else {
				return false;
			}
		}
		
		// Remove a particular session key
		public function remove($key) {
			$_SESSION[$key] = '';
			unset($_SESSION[$key]);
		}
		
		// Destroy all data in the session and then destroy the session content
		public function destroy() {
			// Unset all session keys
			session_unset();
			// Set the $_SESSION as an empty array
			$_SESSION = array();
			// Destroy the session
			session_destroy();
		}
		
		// Used to output a one-time message from $_SESSION['message'] and then delete it to avoid replication
		public function output_message() {
			// First check if there is a message
			if($this->get('message')) {
				// Output the contents of the $_SESSION['message']
				echo $this->get('message');
				// Remove any contents of the message to avoid duplication
				$this->remove('message');
			}
		}
		
		// Construct a Bootstrap alert using a message, and a message type to determine colour/type of message
		public function message_alert($message_content = 'An alert has been called, but not specified!', $message_type = 'warning') {
			// The type of message, determining the colour/type of the alert
			switch($message_type){
				case "success":
					$message = "<div class=\"alert alert-success\" role=\"alert\">";
					break;
				case "info":
					$message = "<div class=\"alert alert-info\" role=\"alert\">";
					break;
				case "warning":
					$message = "<div class=\"alert alert-warning\" role=\"alert\">";
					break;
				case "danger":
				default:
					$message = "<div class=\"alert alert-danger\" role=\"alert\">";
					break;
			};
			
			// Add the content of the message
			$message .= $message_content;
			$message .= "</div>";
			
			// Set the message in the session
			$this->set('message', $message);
		}
		
		// Construct a Bootstrap alert using an array of errors to build a validation failure message
		public function message_validation($errors = array()) {
			// Cycle through an array of validation errors to display a validation failured message to the screen
			$alert = "<div class=\"alert alert-danger\" role=\"alert\">";
			$alert .= "<p>The form was unable to be submitted due to the following errors:<p>";
			$alert .= "<ol>";
			foreach($errors as $error) {
				$alert .= "<li>" . $error . "</li>";
			}
			$alert .= "</ol>";
			$alert .= "<p>Please correct these errors and then try again.</p>";
			$alert .= "</div>";
			
			// Set the validation errors in the session
			$this->set('message', $alert);
		}
		
		// Used to store particular details about the user in the session
		private function obtain_user_details() {
			// Check if the users IP address has been logged
			if(!$this->get('user_ip')) {
				// Log the users IP address
				$this->set('user_ip', $_SERVER['REMOTE_ADDR']);
			}
			// Check if the users HTTP agent has been logged
			if(!$this->get('user_agent')) {
				// Log the users HTTP agent
				$this->set('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
			}
		}
		
	}; // Close class Session
	
// EOF