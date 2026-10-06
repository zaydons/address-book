<?php
	// This class is used to generate random strings, such as IDs and tokens, using a cryptographically secure source
	class Random {

		// Characters which are allowed in generated strings - ambiguous characters such as 'i', 'l', 'o', 'w' and 'W' are excluded
		const CHARACTERS = "abcdefghjkmnpqrstuvxyzABCDEFGHIJKLMNOPQRSTUVXYZ0123456789";

		// Generate a random string of a given length
		public static function string($length) {
			// Initialise a variable used to store the string
			$string = '';
			// Obtain the index of the last allowed character
			$max = strlen(self::CHARACTERS) - 1;

			// Pick each character using random_int() which is suitable for security-sensitive values
			for($i = 0; $i < $length; $i++) {
				$string .= self::CHARACTERS[random_int(0, $max)];
			}

			// Return the string
			return $string;
		}

	}; // Close class Random

// EOF
