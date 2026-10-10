<?php
	// This class is for manipulating data relating to Contacts
	class Contact {
		// Variable to hold a DB instance
		private $db;

		// The fields of a contact which can be entered, with their maximum lengths
		const FIELDS = array(
			'first_name' => 50,
			'middle_name' => 50,
			'last_name' => 50,
			'contact_number_home' => 20,
			'contact_number_mobile' => 20,
			'contact_email' => 100,
			'date_of_birth' => 10,
			'address_line_1' => 100,
			'address_line_2' => 100,
			'address_town' => 100,
			'address_county' => 100,
			'address_post_code' => 20,
		);
		
		// All variables for when all contacts are searched
		public $all = null; // Variable used to hold all contacts
		
		// All variables which are relating to when a user searches for a particular ID
		public $found = false, // Used to check if a contact could be found or not
			   $single = null, // Variable used to hold details of single contact ID
			   $email = null, // Contact email address
			   $date_of_birth = null, // Users date of birth
			   $number = array(), // Used as an array to store various formatted and unformatted phone numbers
			   $full_name = null, // Variable used to hold full name of contact
			   $full_address = null; // Variable used to hold the full address of the contact
			   
		// Constructor
		public function __construct($id = null) {
			// Set the $db with an instance of the database
			$this->db = DB::get_instance();
			
			// If an $id is sent through then return the contact associated with the ID
			// Check if an $id has been sent
			if($id) {
				// $id has been sent, find only that contact
				$this->find_id($id);
			} else {
				// No $id has been sent, find all contacts
				$this->find_all();
			}
		}
		
		// Method to find all contacts in the database
		public function find_all() {
			// Return all 
			return $this->all = $this->db->query('SELECT * FROM contacts', PDO::FETCH_ASSOC);
		}
		
		// Method to find specific contact in the database
		public function find_id($id = null) {
			// Check if $id has been sent
			if($id) {
				// Begin prepared statement to find single ID in database
				$sql = '
					SELECT * FROM contacts 
					WHERE contact_id = :contact_id
				';
				$stmt = $this->db->prepare($sql);
				
				// Pass in the $id into the prepared statement and execute
				$stmt->bindParam(':contact_id', $id);
				$stmt->execute();
				
				// Fetch the results from the prepared statement
				$result = $stmt->fetch();
				
				// Check if a contact could be found
				if($result) {
					// Contact found
					$this->found = true; // Specify that a contact could be found
					// Set the properties of the class as per the users details
					$this->full_name = htmlentities($this->full_name($result));
					$this->full_address = htmlentities($this->full_address($result));
					$this->email = htmlentities($result['contact_email'] ?? '');
					$this->date_of_birth = htmlentities($this->cosmetic_date($result["date_of_birth"]));
					$this->number['home']['raw'] = htmlentities($result['contact_number_home'] ?? '');
					$this->number['home']['formatted'] = htmlentities($this->format_phone_number($result['contact_number_home']));
					$this->number['mobile']['raw'] = htmlentities($result['contact_number_mobile'] ?? '');
					$this->number['mobile']['formatted'] = htmlentities($this->format_phone_number($result['contact_number_mobile']));
					
					// Return all of the details of the contact
					return $this->single = $result;
				} else {
					// Contact not found, return false
					return false;
				}
			} else {
				// $id not sent, return false
				return false;
			}
		}
		
		// Method to find a single contact based on a searched number, listed by alphabetical last name first, then alphabetical first name - used mainly for API call
		// The number can be in any format, such as +1 (212) 555-1234. Numbers with 10 or more digits also match on their last 10 digits,
		// so that a number with or without a country code (such as +12125551234 and 2125551234, or +447700900123 and 07700900123) is found
		public function find_number($number) {
			// Convert the number to the form it is stored in
			$number = self::normalize_phone($number);
			if($number) {
				$digits = preg_replace('/\D/', '', $number);
				$match_last_ten = strlen($digits) >= 10;
				$sql = '
					SELECT first_name, last_name FROM contacts
					WHERE contact_number_home = :number
					OR contact_number_mobile = :number
				';
				if($match_last_ten) {
					$sql .= "
					OR substr(replace(contact_number_home, '+', ''), -10) = :last_ten
					OR substr(replace(contact_number_mobile, '+', ''), -10) = :last_ten
					";
				}
				// Prefer an exact match, then alphabetical by last name and first name
				$sql .= '
					ORDER BY (contact_number_home = :number OR contact_number_mobile = :number) DESC, last_name ASC, first_name ASC
				';
				$stmt = $this->db->prepare($sql);

				// Pass in the number into the prepared statement and execute
				$stmt->bindValue(':number', $number);
				if($match_last_ten) {
					$stmt->bindValue(':last_ten', substr($digits, -10));
				}
				$stmt->execute();

				// Fetch the results from the prepared statement
				$result = $stmt->fetch();

				// Check if a contact could be found
				if($result) {
					// Return only the first and last name of the contact
					return trim($result['first_name'] . ' ' . $result['last_name']);
				} else {
					// Contact not found, return false
					return false;
				}
			} else {
				// $number not sent, return false
				return false;
			}
		}

		// Method to update a particular contact
		public function update($values = array()) {
			// This method will only be called if used from a search of a particular ID during instantiation, such as $contact = new Contact(3298)
			if($this->found) {
				// This method works by accepting a $values array which contains the details of the fields which are to be updated
				// Check that the array isn't empty
				if(!empty($values)) {
					// Array has values, begin building the SQL query to be used to update contact
					$sql = "UPDATE contacts SET ";
					
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
					
					// Specify which contact to update - the ID is unique, so only 1 record is updated
					$sql .= " WHERE contact_id = :contact_id";
					
					// Begin a prepared statement using the previous $sql
					$stmt = $this->db->prepare($sql);
					
					// Pass in values from the $values array to complete the prepared statement
					foreach($values as $key => &$value) {
						$stmt->bindParam(':' . $key, $value);
					}
					// Bind the contact ID to the prepared statement
					$stmt->bindParam(':contact_id', $this->single['contact_id']);
					
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
					// Array was empty
					return false;
				}
			} else {
				// User wasn't found
				return false;
			}
		}

		// Method to delete a particular contact
		public function delete() {
			// This method will only be called if used from a search of a particular ID during instantiation, such as $contact = new Contact(3298)
			if($this->found) {
				// Begin prepared statement to delete a single ID from the database
				$sql = '
					DELETE FROM contacts 
					WHERE contact_id = :contact_id
				';
				$stmt = $this->db->prepare($sql);

				// Pass in the contact_id from the database into the prepared statement and execute
				$stmt->bindParam(':contact_id', $this->single['contact_id']);

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
				// Being called as not part of an ID instance
				return false;
			}
		}
		
		// Method to create a new contact
		public function create($values = array()) {
			// This method works by accepting a $values array which contains the details of the fields which are to be inserted
			// Check that the array isn't empty
			if(!empty($values)) {
				// Obtain a DB instance
				$db = DB::get_instance();
				
				// Array has values, begin building the SQL query to be used to create contact
				$sql = "INSERT INTO contacts (";
				
				// Add in the contact_id as won't be submitted as part of the $values array
				$sql .= "contact_id, ";
				
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
				
				// Add in the contact_id as won't be submitted as part of the $values array
				$sql .= ":contact_id, ";
				
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
				$stmt->bindParam(':contact_id', $id);
				
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
		
		// Format a stored phone number for display, using the PHONE_FORMAT setting:
		//   us   - (212) 555-1234, and +1 (212) 555-1234 for numbers with the +1 country code
		//   uk   - 01234 567890
		//   none - as stored
		// Numbers which don't fit the format, such as international numbers, are shown as stored
		public function format_phone_number($phone_number) {
			$phone_number = self::normalize_phone($phone_number);
			if($phone_number === null) {
				return '';
			}
			$digits = preg_replace('/\D/', '', $phone_number);
			$international = $phone_number[0] === '+';

			switch(strtolower(PHONE_FORMAT)) {
				case 'us':
					if(!$international && strlen($digits) == 10) {
						return '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
					}
					if(strlen($digits) == 11 && $digits[0] === '1') {
						return '+1 (' . substr($digits, 1, 3) . ') ' . substr($digits, 4, 3) . '-' . substr($digits, 7);
					}
					return $phone_number;
				case 'uk':
					if(!$international && strlen($digits) == 11 && $digits[0] === '0') {
						return substr($digits, 0, 5) . ' ' . substr($digits, 5);
					}
					return $phone_number;
				default:
					return $phone_number;
			}
		}

		// Convert a phone number as typed (such as "+1 (212) 555-1234") to the form it is stored in:
		// digits, with a + at the start for an international number. Returns null if there are no digits
		public static function normalize_phone($phone_number) {
			if(!is_string($phone_number)) {
				return null;
			}
			$phone_number = trim($phone_number);
			$digits = preg_replace('/\D/', '', $phone_number);
			if($digits === '') {
				return null;
			}
			return ($phone_number[0] === '+' ? '+' : '') . $digits;
		}

		// Check and tidy the contact fields submitted in a form, or read from an import
		// Returns an array of every field, with empty fields as null, and adds any problems to $errors
		public static function clean_input(array $input, array &$errors) {
			global $validation;
			$values = array();

			foreach(self::FIELDS as $field => $max_length) {
				$value = isset($input[$field]) && is_string($input[$field]) ? trim($input[$field]) : '';

				if($value === '') {
					$values[$field] = null;
					continue;
				}

				switch($field) {
					case 'contact_number_home':
					case 'contact_number_mobile':
						// Digits, spaces and ( ) - . / are allowed, with an optional + at the start
						if(!preg_match('~^\+?[0-9\s().\-/]+$~', $value) || !preg_match('/\d/', $value)) {
							$errors[] = $validation['invalid']['format'][$field];
						}
						$value = self::normalize_phone($value);
						break;
					case 'contact_email':
						if(filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
							$errors[] = $validation['invalid']['format']['contact_email'];
						}
						break;
					case 'date_of_birth':
						// YYYY-MM-DD, as sent by the date picker
						$date = DateTime::createFromFormat('!Y-m-d', $value);
						if(!$date || $date->format('Y-m-d') !== $value) {
							$errors[] = $validation['invalid']['format']['date_of_birth'];
						}
						break;
				}

				if($value !== null && mb_strlen($value) > $max_length && isset($validation['too_long']['contact'][$field])) {
					$errors[] = $validation['too_long']['contact'][$field];
				}
				$values[$field] = $value;
			}

			// A contact needs a first name
			if($values['first_name'] === null) {
				$errors[] = $validation['field_required']['contact']['first_name'];
			}

			return $values;
		}

		public function remove_white_space($string) {
			// Remove all white space within the string
			return preg_replace('/\s+/', '', $string ?? '');
		}
		
		// The contact's name, leaving out any parts which are empty
		public function full_name(array $contact){
			return implode(' ', array_filter(array($contact['first_name'], $contact['middle_name'], $contact['last_name']), function($part) { return $part !== null && $part !== ''; }));
		}

		// The contact's address on one line, leaving out any parts which are empty
		private function full_address(array $contact) {
			return implode(', ', array_filter(array($contact['address_line_1'], $contact['address_line_2'], $contact['address_town'], $contact['address_county'], $contact['address_post_code']), function($part) { return $part !== null && $part !== ''; }));
		}

		private function cosmetic_date($database_date = null) {
			if($database_date) {
				// Convert the database date (YYYY-MM-DD) to a UNIX time stamp
				$unix_date = strtotime($database_date);
				
				// Format date into correct string, example: Saturday 1st May 1993
				$cosmetic_date = date('jS F Y', $unix_date);
				return $cosmetic_date;
			} else {
				return false;
			}
		}
		
	}; // Close class Contact
	
// EOF