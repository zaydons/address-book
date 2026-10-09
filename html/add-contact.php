<?php
	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");
	
	// Set $page_name so that the title of each page is correct
	$page_name = PAGENAME_CONTACTS;
	// Set $subpage_name as this page isn't the main section
	$subpage_name = PAGENAME_CONTACTSADD;
	
	// Check if $user is authenticated
	if(!$user->authenticated) {
		$user->logout('not_authenticated');
	}; // Close if(!$user->authenticated)
	
	// Obtain a CSRF token to be used to prevent CSRF - this is stored in the $_SESSION
	$csrf_token = CSRF::get_token();
	
	// If submit button has been pressed then process the form
	if(isset($_POST["submit"]) && $_POST["submit"] == "submit") {
		
		// Initialise the $errors array where errors will be sent and then retrieved from
		$errors = array();
		
		// Check that the submitted CSRF token is the same as the one in the $_SESSION to prevent cross site request forgery
		if(!CSRF::check_token($_POST['csrf_token'] ?? null))							{ $errors[] = $validation['invalid']['security']['csrf_token']; };
		
		// Check and tidy the submitted fields, such as required fields, lengths and phone number formats
		$fields = Contact::clean_input($_POST, $errors);
		
		// If no errors have been found during the field validations
		if(empty($errors)) {
			
			// Initialise a new Contact object
			$contact = new Contact();
			
			// Create the new contact, inserting the fields from the $fields array
			$result = $contact->create($fields);
			
			if($result){
				// Contact successfully added to the database
				// Log action of add entry success, with contact added 
				// Create new Log instance, and log the action to the database
				$log = new Log('contact_add_success', 'Contact of ' . $contact->full_name($fields) . ' successfully created.');
				// Add session message
				$session->message_alert($notification["contact"]["add"]["success"], "success");
				// Redirect the user
				Redirect::to(PAGELINK_INDEX);
			} else {
				// Add session message
				$session->message_alert($notification["contact"]["add"]["failure"], "danger");
				// Log action of database entry failing
				// Create new Log instance, and log the action to the database
				$log = new Log('contact_add_failed', 'database');
			};
		} else {
			// Form field validation has failed - $errors array is not empty
			// If there are any error messages in the $errors array then display them to the screen
			$session->message_validation($errors);
			// Log action of failing form process
			// Create new Log instance, and log the action to the database
			$log = new Log('contact_add_failed', 'Failed user add due to form validation errors.');
		};
	} else {
		// Form has not been submitted
		// Log action of accessing the page
		// Create new Log instance, and log the page view to the database
		$log = new Log('view');
	};
	
	// Require head content in the page
	require_once("../includes/layout.head.inc.php");
	// Requre navigation content in the page
	require_once("../includes/layout.navigation.inc.php");
?>
	
			<!-- CONTENT -->
			<?php $session->output_message(); ?>
			
			<form action="" method="post">
				
				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">First Name</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="first_name" placeholder="First Name" maxlength="50" <?php if(isset($_POST["first_name"])){ echo "value=\"" . htmlentities($_POST["first_name"]) . "\""; }; ?> required>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Middle Name</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="middle_name" placeholder="Middle Name" maxlength="50" <?php if(isset($_POST["middle_name"])){ echo "value=\"" . htmlentities($_POST["middle_name"]) . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Last Name</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="last_name" placeholder="Last Name" maxlength="50" <?php if(isset($_POST["last_name"])){ echo "value=\"" . htmlentities($_POST["last_name"]) . "\""; }; ?>>
					</div>
				</div>
				
				<hr>
				
				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Contact Number Home</label>
					<div class="col-sm-4">
						<input type="tel" inputmode="tel" autocomplete="tel" class="form-control" name="contact_number_home" placeholder="Contact Number Home" maxlength="25" <?php if(isset($_POST["contact_number_home"])){ echo "value=\"" . htmlentities($_POST["contact_number_home"]) . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Contact Number Mobile</label>
					<div class="col-sm-4">
						<input type="tel" inputmode="tel" autocomplete="tel" class="form-control" name="contact_number_mobile" placeholder="Contact Number Mobile" maxlength="25" <?php if(isset($_POST["contact_number_mobile"])){ echo "value=\"" . htmlentities($_POST["contact_number_mobile"]) . "\""; }; ?>>
					</div>
				</div>
				
				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Email</label>
					<div class="col-sm-4">
						<input type="email" class="form-control" name="contact_email" placeholder="Email" maxlength="100" <?php if(isset($_POST["contact_email"])){ echo "value=\"" . htmlentities($_POST["contact_email"]) . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Date Of Birth</label>
					<div class="col-sm-4">
						<input type="date" class="form-control" name="date_of_birth" placeholder="Date Of Birth" <?php if(isset($_POST["date_of_birth"])){ echo "value=\"" . htmlentities($_POST["date_of_birth"]) . "\""; }; ?>>
					</div>
				</div>
				
				<hr>
				
				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Line 1</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_line_1" placeholder="Address Line 1" maxlength="100" <?php if(isset($_POST["address_line_1"])){ echo "value=\"" . htmlentities($_POST["address_line_1"]) . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Line 2</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_line_2" placeholder="Address Line 2" maxlength="100" <?php if(isset($_POST["address_line_2"])){ echo "value=\"" . htmlentities($_POST["address_line_2"]) . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Town</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_town" placeholder="Address Town" maxlength="100" <?php if(isset($_POST["address_town"])){ echo "value=\"" . htmlentities($_POST["address_town"]) . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address County</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_county" placeholder="Address County" maxlength="100" <?php if(isset($_POST["address_county"])){ echo "value=\"" . htmlentities($_POST["address_county"]) . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Postcode</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_post_code" placeholder="Address Postcode" maxlength="20" <?php if(isset($_POST["address_post_code"])){ echo "value=\"" . htmlentities($_POST["address_post_code"]) . "\""; }; ?>>
					</div>
				</div>
				
				<input type="hidden" name="csrf_token" value="<?php echo htmlentities($csrf_token); ?>"/>
				
				<div class="row gy-2 mb-3">
					<div class="offset-sm-2 col-sm-10">
						<button type="submit" name="submit" value="submit" class="btn btn-primary">Submit</button>
					</div>
				</div>
			</form>
			<!-- /CONTENT -->

<?php
	// Requre footer content in the page, including any relevant scripts
	require_once("../includes/layout.footer.inc.php");
?>