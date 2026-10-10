<?php
	// This class is used to export contacts to, and import contacts from, CSV and vCard (.vcf) files
	class ContactTransfer {

		// The most contacts which can be imported from one file
		const MAX_IMPORT = 5000;

		// The CSV columns, in order, and the contact field each one holds
		const CSV_COLUMNS = array(
			'First Name' => 'first_name',
			'Middle Name' => 'middle_name',
			'Last Name' => 'last_name',
			'Home Phone' => 'contact_number_home',
			'Mobile Phone' => 'contact_number_mobile',
			'Email' => 'contact_email',
			'Date Of Birth' => 'date_of_birth',
			'Address Line 1' => 'address_line_1',
			'Address Line 2' => 'address_line_2',
			'Town' => 'address_town',
			'County' => 'address_county',
			'Post Code' => 'address_post_code',
		);

		// Other column names which are recognised when importing a CSV file, such as from other address books or spreadsheets
		const CSV_ALIASES = array(
			'first_name' => array('first name', 'firstname', 'given name', 'forename'),
			'middle_name' => array('middle name', 'middlename', 'additional name'),
			'last_name' => array('last name', 'lastname', 'surname', 'family name'),
			'contact_number_home' => array('home phone', 'home', 'phone home', 'home number', 'contact number home', 'telephone'),
			'contact_number_mobile' => array('mobile phone', 'mobile', 'cell', 'cell phone', 'mobile number', 'contact number mobile', 'phone'),
			'contact_email' => array('email', 'e mail', 'email address', 'e mail address', 'contact email'),
			'date_of_birth' => array('date of birth', 'birthday', 'dob', 'birth date'),
			'address_line_1' => array('address line 1', 'address 1', 'street', 'street address', 'address'),
			'address_line_2' => array('address line 2', 'address 2'),
			'address_town' => array('town', 'city', 'address town'),
			'address_county' => array('county', 'state', 'region', 'province', 'address county'),
			'address_post_code' => array('post code', 'postcode', 'postal code', 'zip', 'zip code', 'address post code'),
		);

		// ---- Export ----

		// Create a CSV file of the contacts, which opens in spreadsheet programs such as Excel
		public static function to_csv(array $contacts) {
			$formatter = new Contact(false);
			$handle = fopen('php://temp', 'r+');
			// A byte order mark tells Excel that the file is UTF-8, so that accented characters are shown correctly
			fwrite($handle, "\xEF\xBB\xBF");
			fputcsv($handle, array_keys(self::CSV_COLUMNS), ',', '"', '');
			foreach($contacts as $contact) {
				$row = array();
				foreach(self::CSV_COLUMNS as $field) {
					$value = $contact[$field] ?? '';
					// Phone numbers are written as they are shown, so that spreadsheets keep them as text (with any leading 0)
					if($field == 'contact_number_home' || $field == 'contact_number_mobile') {
						$value = $formatter->format_phone_number($value);
					}
					$row[] = self::csv_safe($value);
				}
				fputcsv($handle, $row, ',', '"', '');
			}
			rewind($handle);
			$csv = stream_get_contents($handle);
			fclose($handle);
			return $csv;
		}

		// Stop a value being run as a formula when the file is opened in a spreadsheet program, by starting it with a '
		private static function csv_safe($value) {
			$value = (string) $value;
			if($value !== '' && strpos("=+-@\t\r", $value[0]) !== false) {
				return "'" . $value;
			}
			return $value;
		}

		// Create a vCard (.vcf) file of the contacts, which can be imported into phones and other address books
		public static function to_vcard(array $contacts) {
			$formatter = new Contact(false);
			$cards = '';
			foreach($contacts as $contact) {
				$lines = array('BEGIN:VCARD', 'VERSION:3.0');
				$lines[] = 'N:' . implode(';', array_map(array(__CLASS__, 'vcard_escape'), array($contact['last_name'] ?? '', $contact['first_name'] ?? '', $contact['middle_name'] ?? '', '', '')));
				$lines[] = 'FN:' . self::vcard_escape($formatter->full_name($contact));
				if(!empty($contact['contact_number_mobile'])) {
					$lines[] = 'TEL;TYPE=CELL:' . self::vcard_escape($formatter->format_phone_number($contact['contact_number_mobile']));
				}
				if(!empty($contact['contact_number_home'])) {
					$lines[] = 'TEL;TYPE=HOME,VOICE:' . self::vcard_escape($formatter->format_phone_number($contact['contact_number_home']));
				}
				if(!empty($contact['contact_email'])) {
					$lines[] = 'EMAIL;TYPE=INTERNET:' . self::vcard_escape($contact['contact_email']);
				}
				if(!empty($contact['date_of_birth'])) {
					$lines[] = 'BDAY:' . $contact['date_of_birth'];
				}
				$street = implode("\n", array_filter(array($contact['address_line_1'] ?? '', $contact['address_line_2'] ?? ''), 'strlen'));
				if($street !== '' || !empty($contact['address_town']) || !empty($contact['address_county']) || !empty($contact['address_post_code'])) {
					// ADR is: post office box; extended address; street; town; region; post code; country
					$lines[] = 'ADR;TYPE=HOME:;;' . implode(';', array_map(array(__CLASS__, 'vcard_escape'), array($street, $contact['address_town'] ?? '', $contact['address_county'] ?? '', $contact['address_post_code'] ?? '', '')));
				}
				$lines[] = 'END:VCARD';
				foreach($lines as $line) {
					$cards .= self::vcard_fold($line) . "\r\n";
				}
			}
			return $cards;
		}

		// Escape the characters which have a special meaning in vCard values
		private static function vcard_escape($value) {
			return str_replace(array('\\', "\r\n", "\n", ',', ';'), array('\\\\', '\\n', '\\n', '\\,', '\\;'), (string) $value);
		}

		// vCard lines should be at most 75 bytes, with longer lines continued on the next line after a space
		// Lines are only split between characters, never in the middle of an accented character
		private static function vcard_fold($line) {
			$folded = '';
			$length = 0;
			foreach(preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $character) {
				if($length + strlen($character) > 75) {
					$folded .= "\r\n ";
					$length = 1;
				}
				$folded .= $character;
				$length += strlen($character);
			}
			return $folded;
		}

		// ---- Import ----

		// Read contacts from the contents of a CSV or vCard file
		// Returns an array of contacts, each with 'line' (where it was found, for messages) and 'fields' (contact field => value),
		// or throws an InvalidArgumentException if the file can't be read
		public static function parse($contents) {
			// Remove a UTF-8 byte order mark, and convert other text encodings (such as from older versions of Excel) to UTF-8
			if(substr($contents, 0, 3) === "\xEF\xBB\xBF") {
				$contents = substr($contents, 3);
			}
			if(!mb_check_encoding($contents, 'UTF-8')) {
				$contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
			}
			if(preg_match('/^\s*BEGIN:VCARD/mi', $contents)) {
				return self::parse_vcard($contents);
			}
			return self::parse_csv($contents);
		}

		// Read contacts from a CSV file with a header row naming the columns
		private static function parse_csv($contents) {
			$lines = preg_split('/\r\n|\n|\r/', $contents, 2);
			$header_line = $lines[0] ?? '';
			// Spreadsheets in some countries save CSV files separated by ; rather than ,
			$delimiter = substr_count($header_line, ';') > substr_count($header_line, ',') ? ';' : ',';

			$handle = fopen('php://temp', 'r+');
			fwrite($handle, $contents);
			rewind($handle);

			$header = fgetcsv($handle, null, $delimiter, '"', '');
			if(!$header) {
				throw new InvalidArgumentException('The file is empty.');
			}
			// Work out which contact field each column holds
			$columns = array();
			foreach($header as $index => $name) {
				$name = trim(strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string) $name)));
				foreach(self::CSV_ALIASES as $field => $aliases) {
					if(($name === str_replace('_', ' ', $field) || in_array($name, $aliases, true)) && !in_array($field, $columns, true)) {
						$columns[$index] = $field;
						break;
					}
				}
			}
			if(!in_array('first_name', $columns, true) && !in_array('last_name', $columns, true)) {
				fclose($handle);
				throw new InvalidArgumentException('The CSV file needs a header row with column names, including a "First Name" or "Last Name" column. Export a CSV file from the system to see the columns it uses.');
			}

			$contacts = array();
			$line = 1;
			while(($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
				$line++;
				// Skip empty rows
				if(count(array_filter($row, function($value) { return trim((string) $value) !== ''; })) == 0) {
					continue;
				}
				$fields = array();
				foreach($columns as $index => $field) {
					$value = trim((string) ($row[$index] ?? ''));
					// Remove the ' which stops a value being run as a formula (see csv_safe())
					if(strlen($value) > 1 && $value[0] === "'" && strpos("=+-@", $value[1]) !== false) {
						$value = substr($value, 1);
					}
					$fields[$field] = $value;
				}
				$contacts[] = array('line' => 'Row ' . $line, 'fields' => self::fill_name($fields));
			}
			fclose($handle);
			return $contacts;
		}

		// Read contacts from a vCard file, which can hold many contacts (versions 2.1, 3.0 and 4.0)
		private static function parse_vcard($contents) {
			// Join folded lines, which continue on the next line starting with a space or tab
			$contents = preg_replace('/\r\n|\r/', "\n", $contents);
			$contents = preg_replace('/\n[ \t]/', '', $contents);
			$lines = explode("\n", $contents);

			$contacts = array();
			$card = null;
			$number = 0;
			for($i = 0; $i < count($lines); $i++) {
				$line = $lines[$i];
				// A quoted-printable value (used by vCard 2.1, such as from Android phones) continues on the next line when it ends with =
				// Only these lines are joined, as other values (such as photos) can end with = too
				$colon = strpos($line, ':');
				if($colon !== false && stripos(substr($line, 0, $colon), 'QUOTED-PRINTABLE') !== false) {
					while(substr($line, -1) === '=' && $i + 1 < count($lines)) {
						$line = substr($line, 0, -1) . $lines[++$i];
					}
				}
				if(trim($line) === '') {
					continue;
				}
				if(stripos($line, 'BEGIN:VCARD') === 0) {
					$number++;
					$card = array('fields' => array(), 'phones' => array(), 'fn' => '');
					continue;
				}
				if(stripos($line, 'END:VCARD') === 0) {
					if($card !== null) {
						$contacts[] = array('line' => 'Contact ' . $number, 'fields' => self::finish_card($card));
					}
					$card = null;
					continue;
				}
				if($card === null || strpos($line, ':') === false) {
					continue;
				}

				// A line is NAME;PARAMETERS:VALUE, and the name can start with a group, such as item1.TEL
				list($name_and_parameters, $value) = explode(':', $line, 2);
				$parts = explode(';', $name_and_parameters);
				$name = strtoupper(preg_replace('/^.*\./', '', array_shift($parts)));
				$types = array();
				$encoding = '';
				$charset = '';
				foreach($parts as $parameter) {
					$parameter_parts = explode('=', $parameter, 2);
					$key = strtoupper(trim($parameter_parts[0]));
					$parameter_value = $parameter_parts[1] ?? null;
					if($parameter_value === null) {
						// vCard 2.1 lists types on their own, such as TEL;CELL
						$types[] = $key;
					} elseif($key === 'TYPE') {
						foreach(explode(',', trim($parameter_value, '"')) as $type) {
							$types[] = strtoupper($type);
						}
					} elseif($key === 'ENCODING') {
						$encoding = strtoupper($parameter_value);
					} elseif($key === 'CHARSET') {
						$charset = strtoupper($parameter_value);
					}
				}

				// Decode the value
				if($encoding === 'QUOTED-PRINTABLE') {
					$value = quoted_printable_decode($value);
				}
				if($charset !== '' && $charset !== 'UTF-8' && in_array($charset, array_map('strtoupper', mb_list_encodings()), true)) {
					$value = mb_convert_encoding($value, 'UTF-8', $charset);
				}

				switch($name) {
					case 'N':
						$name_parts = self::vcard_split($value);
						$card['fields']['last_name'] = $name_parts[0] ?? '';
						$card['fields']['first_name'] = $name_parts[1] ?? '';
						$card['fields']['middle_name'] = $name_parts[2] ?? '';
						break;
					case 'FN':
						$card['fn'] = self::vcard_unescape($value);
						break;
					case 'TEL':
						$card['phones'][] = array('number' => self::vcard_unescape(preg_replace('/^tel:/i', '', $value)), 'types' => $types);
						break;
					case 'EMAIL':
						if(empty($card['fields']['contact_email'])) {
							$card['fields']['contact_email'] = self::vcard_unescape($value);
						}
						break;
					case 'BDAY':
						$card['fields']['date_of_birth'] = self::vcard_date($value);
						break;
					case 'ADR':
						// Use the home address if there is one, otherwise the first address
						if(!isset($card['address']) || (in_array('HOME', $types, true) && !$card['address_is_home'])) {
							$card['address'] = self::vcard_split($value);
							$card['address_is_home'] = in_array('HOME', $types, true);
						}
						break;
				}
			}
			return $contacts;
		}

		// Turn what was read from a vCard into contact fields
		private static function finish_card(array $card) {
			$fields = $card['fields'];

			// Phone numbers: mobile from a CELL, MOBILE or IPHONE number, home from a HOME number, then any others in order
			$unused = array();
			foreach($card['phones'] as $phone) {
				if(empty($fields['contact_number_mobile']) && array_intersect($phone['types'], array('CELL', 'MOBILE', 'IPHONE'))) {
					$fields['contact_number_mobile'] = $phone['number'];
				} elseif(empty($fields['contact_number_home']) && in_array('HOME', $phone['types'], true) && !in_array('FAX', $phone['types'], true)) {
					$fields['contact_number_home'] = $phone['number'];
				} elseif(!in_array('FAX', $phone['types'], true)) {
					$unused[] = $phone['number'];
				}
			}
			foreach($unused as $number) {
				if(empty($fields['contact_number_mobile'])) {
					$fields['contact_number_mobile'] = $number;
				} elseif(empty($fields['contact_number_home'])) {
					$fields['contact_number_home'] = $number;
				}
			}

			// Address: the street can be on more than one line
			if(isset($card['address'])) {
				$street = preg_split('/\n/', trim($card['address'][2] ?? ''));
				$fields['address_line_1'] = $street[0] ?? '';
				$fields['address_line_2'] = implode(', ', array_slice($street, 1));
				$fields['address_town'] = $card['address'][3] ?? '';
				$fields['address_county'] = $card['address'][4] ?? '';
				$fields['address_post_code'] = $card['address'][5] ?? '';
			}

			// Use the formatted name if the card has no separate name parts
			if(empty($fields['first_name']) && empty($fields['last_name']) && $card['fn'] !== '') {
				$name_parts = preg_split('/\s+/', trim($card['fn']), 2);
				$fields['first_name'] = $name_parts[0];
				$fields['last_name'] = $name_parts[1] ?? '';
			}

			return self::fill_name($fields);
		}

		// A contact needs a first name, so a contact with only a last name (such as a business) uses it as the first name
		private static function fill_name(array $fields) {
			if(trim($fields['first_name'] ?? '') === '' && trim($fields['last_name'] ?? '') !== '') {
				$fields['first_name'] = $fields['last_name'];
				$fields['last_name'] = '';
			}
			return $fields;
		}

		// Split a vCard value on the ; between its parts, and unescape each part
		private static function vcard_split($value) {
			return array_map(array(__CLASS__, 'vcard_unescape'), preg_split('/(?<!\\\\);/', $value));
		}

		// Undo the escaping of special characters in a vCard value
		private static function vcard_unescape($value) {
			return trim(preg_replace_callback('/\\\\(.)/', function($match) {
				return strtolower($match[1]) === 'n' ? "\n" : $match[1];
			}, $value));
		}

		// Read a vCard date (such as 1964-04-23, 19640423 or 1964-04-23T00:00:00Z) as YYYY-MM-DD. Dates without a year are ignored
		private static function vcard_date($value) {
			if(preg_match('/^(\d{4})-?(\d{2})-?(\d{2})/', trim($value), $match)) {
				return $match[1] . '-' . $match[2] . '-' . $match[3];
			}
			return '';
		}

		// ---- Saving imported contacts ----

		// Add imported contacts to the address book
		// Returns the number added, the number skipped as already in the address book, and a list of problems
		public static function import(array $contacts, $skip_duplicates = true) {
			$result = array('added' => 0, 'duplicates' => 0, 'problems' => array());
			if(count($contacts) > self::MAX_IMPORT) {
				$result['problems'][] = 'The file has ' . count($contacts) . ' contacts, but at most ' . self::MAX_IMPORT . ' can be imported at once. Split it into smaller files.';
				return $result;
			}

			$db = DB::get_instance();
			$contact = new Contact(false);
			$existing = $skip_duplicates ? array_flip(array_map(array(__CLASS__, 'duplicate_key'), $db->query('SELECT * FROM contacts')->fetchAll(PDO::FETCH_ASSOC))) : array();

			// Add all of the contacts at once, which is much faster than one at a time
			$db->exec('BEGIN IMMEDIATE');
			try {
				foreach($contacts as $item) {
					$errors = array();
					$fields = Contact::clean_input($item['fields'], $errors);
					$name = $contact->full_name($fields);
					if(!empty($errors)) {
						$result['problems'][] = $item['line'] . ($name !== '' ? ' (' . $name . ')' : '') . ': ' . implode(' ', $errors);
						continue;
					}
					if($skip_duplicates) {
						$key = self::duplicate_key($fields);
						if(isset($existing[$key])) {
							$result['duplicates']++;
							continue;
						}
						// Also skip a contact which appears twice in the same file
						$existing[$key] = true;
					}
					$contact->create($fields);
					$result['added']++;
				}
				$db->exec('COMMIT');
			} catch(Exception $error) {
				$db->exec('ROLLBACK');
				throw $error;
			}
			return $result;
		}

		// Contacts are treated as the same if they have the same name (ignoring capitals) and the same phone numbers and email address
		private static function duplicate_key(array $contact) {
			return mb_strtolower(implode('|', array(
				trim($contact['first_name'] ?? ''),
				trim($contact['last_name'] ?? ''),
				Contact::normalize_phone($contact['contact_number_mobile'] ?? null),
				Contact::normalize_phone($contact['contact_number_home'] ?? null),
				trim($contact['contact_email'] ?? ''),
			)));
		}

	}; // Close class ContactTransfer

// EOF
