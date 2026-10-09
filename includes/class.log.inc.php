<?php
	// This class is used for logging user actions to the database in the logs table
	class Log {
		
		private $db = null; // Used to store an instance of the database
		
		// Constructor
		public function __construct($action = null, $additional_message = null) {
			// Obtain an instance of the database
			$this->db = DB::get_instance();
			
			// If action was sent then process a new action to be added to the database
			if($action) {
				// $action has been sent, add to the database
				$this->action($action, $additional_message);
			}
		}
		
		// The columns of the logs table which can be shown, searched and sorted, in the order shown on the Logs page
		const COLUMNS = array('datetime', 'action', 'user', 'ip');
		
		// Find one page of log entries, for the Logs page
		// $search is text to look for in any column, $order_column is an index of COLUMNS
		// Returns the total number of entries, the number matching the search, and the entries on this page
		public function find_page($start, $length, $search = '', $order_column = 0, $order_descending = true) {
			$where = '';
			$parameters = array();
			if($search !== '') {
				// Match the search text anywhere in any column, treating % and _ as ordinary characters
				$where = ' WHERE ' . implode(' OR ', array_map(function($column) { return $column . " LIKE :search ESCAPE '\\'"; }, self::COLUMNS));
				$parameters[':search'] = '%' . addcslashes($search, '%_\\') . '%';
			}
			$order = self::COLUMNS[$order_column] ?? 'datetime';
			$direction = $order_descending ? 'DESC' : 'ASC';
			
			$total = (int) $this->db->query('SELECT COUNT(*) FROM logs')->fetchColumn();
			$count = $this->db->prepare('SELECT COUNT(*) FROM logs' . $where);
			$count->execute($parameters);
			$filtered = (int) $count->fetchColumn();
			
			// Entries logged in the same second are shown in the order they were logged
			$rows = $this->db->prepare('SELECT ' . implode(', ', self::COLUMNS) . ' FROM logs' . $where . ' ORDER BY ' . $order . ' ' . $direction . ', log_id ' . $direction . ' LIMIT :limit OFFSET :offset');
			foreach($parameters as $name => $value) {
				$rows->bindValue($name, $value);
			}
			$rows->bindValue(':limit', $length, PDO::PARAM_INT);
			$rows->bindValue(':offset', $start, PDO::PARAM_INT);
			$rows->execute();
			
			return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows->fetchAll(PDO::FETCH_NUM));
		}
		
		// Remove log entries older than LOG_RETENTION_DAYS days
		public function remove_old() {
			$days = (int) LOG_RETENTION_DAYS;
			if($days <= 0) {
				return 0;
			}
			$delete = $this->db->prepare('DELETE FROM logs WHERE datetime < :cutoff');
			$delete->execute(array(':cutoff' => date('Y-m-d H:i:s', time() - $days * 86400)));
			return $delete->rowCount();
		}
		
		// Count the failed logins since a given time, either from an IP address or for a username
		public function count_failed_logins($since, $ip = null, $username = null) {
			// Failed logins are recorded with an action starting "Login Failed"
			$sql = "SELECT COUNT(*) FROM logs WHERE datetime >= :since ";
			if($ip !== null) {
				$sql .= "AND ip = :ip AND action LIKE 'Login Failed%'";
			} else {
				// Only failed passwords record the username, so match that exact action
				$sql .= "AND action = :action";
			}
			$stmt = $this->db->prepare($sql);
			
			// Bind values to the prepared statement
			$stmt->bindValue(':since', date('Y-m-d H:i:s', $since));
			if($ip !== null) {
				$stmt->bindValue(':ip', $ip);
			} else {
				$stmt->bindValue(':action', $this->get_action('login_failed', 'Failed authentication for username: ' . $username));
			}
			$stmt->execute();
			
			// Return the number of failed logins found
			return (int) $stmt->fetchColumn();
		}
		
		// Method to add a new entry to the logs table in the database
		public function action($action = null, $additional_message = null) {
			global $user;
			
			// Define the SQL to be used to make changes to the database
			$sql = '
				INSERT INTO logs ( 
					datetime, 
					action, 
					url, 
					user, 
					ip, 
					user_agent 
				) VALUES ( 
					:datetime, 
					:action, 
					:url, 
					:user, 
					:ip, 
					:user_agent 
				)
			';
			
			// Begin a prepared statement using the previous $sql
			$stmt = $this->db->prepare($sql);
			
			// Bind values to the prepared statement
			$datetime = $this->current_datetime();
			$stmt->bindParam(':datetime', $datetime);
			$action = $this->get_action($action, $additional_message);
			$stmt->bindParam(':action', $action);
			$url = site_url() . $_SERVER['REQUEST_URI'];
			// Hide any API token sent in the URL so that it isn't stored in the logs
			$url = preg_replace('/([?&]t=)[^&]*/', '$1[hidden]', $url);
			$stmt->bindParam(':url', $url);
			$name = $user->username ? $user->name . ' [' . $user->username . ']' : 'Unknown';
			$stmt->bindParam(':user', $name);
			$stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
			$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$stmt->bindParam(':user_agent', $user_agent);
			
			// Execute the prepared statement
			$result = $stmt->execute();
			
			// Every so often, remove old log entries so that the logs don't grow forever
			if(random_int(1, 100) === 1) {
				$this->remove_old();
			}
			
			// Check if successful
			if($result) {
				// Insert successful
				return true;
			} else {
				// Insert failed
				return false;
			}
		}
		
		// Returns a string of an action, based on an input
		private function get_action($action = null, $additional_message = null) {
			// Run through a switch statement to specify an action and return
			switch($action) {
				case 'view' : // For page views
					$action = 'Page Viewed: (' . page_name() . ')'; // Use the page_name function to specify which page a user has visited
					break;
				case 'not_found' : // For accessing pages which couldn't be found, such as invalid $_GET values or values which couldn't be found in the database
					$action = "Result Not Found: (" . page_name() . ')';
					break;
				case 'login_failed' :
					$action = 'Login Failed';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'login_success' :
					$action = 'Login Success';
					break;
				case 'login_locked' :
					$action = 'Login Blocked: Too many failed login attempts';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'password_change_success' :
					$action = 'Password Change Success';
					break;
				case 'password_change_failed' :
					$action = 'Password Change Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'login_redirect' :
					$action = 'User redirected from login page due to already being logged in';
					break;
				case 'logout_success' :
					$action = 'Logout Success';
					break;
				case 'logout_security' :
					$action = 'Automatic logout due to a failed security check';
					break;
				case 'not_authenticated' :
					$action = 'Unauthenticated User Attempted Accessing Page: (' . page_name() . ')';
					break;
				case 'user_add_failed' :
					$action = 'User Add Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'user_add_success' :
					$action = 'User Add Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'user_delete_failed' :
					$action = 'User Delete Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'user_delete_success' :
					$action = 'User Delete Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'user_update_failed' :
					$action = 'User Update Failed';
					if($additional_message == 'database_password') {
						$action .= ': There was an error making changes to the database to update password.';
					} elseif($additional_message == 'database_details') {
						$action .= ': There was an error making changes to the database to update details.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'user_update_success' :
					$action = 'User Update Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'contact_add_failed' :
					$action = 'Contact Add Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'contacts_import' :
					$action = 'Contacts Imported';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'contacts_export' :
					$action = 'Contacts Exported';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'contact_add_success' :
					$action = 'Contact Add Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'contact_delete_failed' :
					$action = 'Contact Delete Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'contact_delete_success' :
					$action = 'Contact Delete Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'contact_update_failed' :
					$action = 'Contact Update Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'contact_update_success' :
					$action = 'Contact Update Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'api_call_success' :
					$action = 'API Call Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'api_call_failed' :
					$action = 'API Call Failed';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'api_add_failed' :
					$action = 'API Token Add Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'api_add_success' :
					$action = 'API Token Add Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'api_delete_failed' :
					$action = 'API Token Delete Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'api_delete_success' :
					$action = 'API Token Delete Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				case 'api_update_failed' :
					$action = 'API Token Update Failed';
					if($additional_message == 'database') {
						$action .= ': There was an error making changes to the database.';
					} elseif($additional_message) {
						$action .= ': ' . $additional_message;
					}
					break;
				case 'api_update_success' :
					$action = 'API Token Update Success';
					if($additional_message) {
						$action .= ': ' . $additional_message;
					};
					break;
				default :
					$action = 'Action Unspecified!';
					break;
			}
			
			// Return the $action
			return $action;
		}
		
		// Method to obtain the current datetime in the format stored in the database
		private function current_datetime() {
			// Return the current time in the database datetime format (YYYY-MM-DD HH:MM:SS) 
			return date('Y-m-d H:i:s', time());
		}
		
	}; // Close class Log
// EOF