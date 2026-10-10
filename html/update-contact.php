<?php
	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");
	
	// Set $page_name so that the title of each page is correct
	$page_name = PAGENAME_CONTACTS;	
	
	// Check if $user is authenticated
	if(!$user->authenticated) {
		$user->logout('not_authenticated');
	}; // Close if(!$user->authenticated)

	// If the value of i in GET exists
	if(isset($_GET['i'])) {
		
		// Find contact in database
		$contact = new Contact($_GET['i']);
		
		// If a contact is found in the database
		if($contact->found) {
			
			// Obtain a CSRF token to be used to prevent CSRF - this is stored in the $_SESSION
			$csrf_token = CSRF::get_token();
			
			// Set page name as contact could be found
			$subpage_name = $contact->full_name . ' - ' . PAGENAME_CONTACTSUPDATE;
		
			// The values shown in the form: what was submitted if the form has been sent, otherwise what is saved
			$submitted = isset($_POST["submit"]) && $_POST["submit"] == "submit";
			foreach(array_keys(Contact::FIELDS) as $field) {
				if($submitted) {
					$value = isset($_POST[$field]) && is_string($_POST[$field]) ? $_POST[$field] : '';
				} elseif($field == 'contact_number_home' || $field == 'contact_number_mobile') {
					$value = $contact->format_phone_number($contact->single[$field]);
				} else {
					$value = $contact->single[$field] ?? '';
				}
				// Sets $form_first_name, $form_last_name and so on, used in the form below
				${'form_' . $field} = htmlentities($value);
			}
			
			// Check that the user has submitted the form
			if($submitted) {
				// Initialise the $errors array where errors will be sent and then retrieved from
				$errors = array();
				
				// Check that the submitted CSRF token is the same as the one in the $_SESSION to prevent cross site request forgery
				if(!CSRF::check_token($_POST['csrf_token'] ?? null))									{ $errors[] = $validation['invalid']['security']['csrf_token']; };
				
				// Check and tidy the submitted fields, such as required fields, lengths and phone number formats
				// Every field is updated, so a field which has been cleared in the form is cleared on the contact
				$update_values = Contact::clean_input($_POST, $errors);
				
				// If no errors have been found during the field validations
				if(empty($errors)) {
					
					// Execute the update
					$result = $contact->update($update_values);
					
					// Check if the update was successful
					if($result){
						// Contact successfully updated on the database
						// Set session message
						$session->message_alert($notification["contact"]["update"]["success"], "success");
						// Log action of add entry success, with contact updated
						// Create new Log instance, and log the action to the database
						$log = new Log('contact_update_success',  $contact->full_name . " from " . $contact->single['address_town'] . " (" . $_GET['i'] . ")");
						// Redirect the user
						Redirect::to(PAGELINK_INDEX);
					} else {
						// Set session message
						$session->message_alert($notification["contact"]["update"]["failure"], "danger");
						// Log action of database entry failing
						// Create new Log instance, and log the action to the database
						$log = new Log('contact_update_failed', 'database');
					};
					
				} else {
					// Form field validation has failed - $errors array is not empty
					// If there are any error messages in the $errors array then display them to the screen
					$session->message_validation($errors);
					// Log action of failing form process
					// Create new Log instance, and log the action to the database
					$log = new Log('contact_update_failed', 'Failed contact update due to form validation errors.');
				};
				
			}; // User has not submitted the form - do nothing
			
			// User has accessed the page and not sumitted the form
			// Create new Log instance, and log the page view to the database
			$log = new Log('view');
		} else {
			// Contact could not be found in the database
			// Set $subpage_name so that the title of each page is correct - contact couldn't be found
			$subpage_name = 'Contact Not Found - ' . PAGENAME_CONTACTSUPDATE;
			// Send message and redirect
			$session->message_alert($notification["contact"]["update"]["not_found"], "danger");
			// Log user accessing incorrect GET value
			// Create new Log instance, and log the action to the database
			$log = new Log('not_found');
			// Redirect the user
			Redirect::to(PAGELINK_INDEX);
		};
	} else {
		// Value of i in GET doesn't exist, send message and redirect
		// Set $subpage_name so that the title of each page is correct - GET value not correct
		$subpage_name = 'Invalid GET Value - ' . PAGENAME_CONTACTSUPDATE;
		$session->message_alert($notification["contact"]["update"]["not_found"], "danger");
		// Log user accessing incorrect GET key
		// Create new Log instance, and log the action to the database
		$log = new Log('not_found');
		// Redirect the user
		Redirect::to(PAGELINK_INDEX);
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
						<input type="text" class="form-control" name="first_name" placeholder="First Name" maxlength="50" <?php if(!empty($form_first_name)) { echo "value=\"" . $form_first_name . "\""; }; ?> required>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Middle Name</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="middle_name" placeholder="Middle Name" maxlength="50" <?php if(!empty($form_middle_name)) { echo "value=\"" . $form_middle_name . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Last Name</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="last_name" placeholder="Last Name" maxlength="50" <?php if(!empty($form_last_name)) { echo "value=\"" . $form_last_name . "\""; }; ?>>
					</div>
				</div>
				
				<hr>
				
				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Contact Number Home</label>
					<div class="col-sm-4">
						<input type="tel" inputmode="tel" autocomplete="tel" class="form-control" name="contact_number_home" placeholder="Contact Number Home" maxlength="25" <?php if(!empty($form_contact_number_home)) { echo "value=\"" . $form_contact_number_home . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Contact Number Mobile</label>
					<div class="col-sm-4">
						<input type="tel" inputmode="tel" autocomplete="tel" class="form-control" name="contact_number_mobile" placeholder="Contact Number Mobile" maxlength="25" <?php if(!empty($form_contact_number_mobile)) { echo "value=\"" . $form_contact_number_mobile . "\""; }; ?>>
					</div>
				</div>
				
				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Email</label>
					<div class="col-sm-4">
						<input type="email" class="form-control" name="contact_email" placeholder="Email" maxlength="100" <?php if(!empty($form_contact_email)) { echo "value=\"" . $form_contact_email . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Date Of Birth</label>
					<div class="col-sm-4">
						<input type="date" class="form-control" name="date_of_birth" placeholder="Date Of Birth" <?php if(!empty($form_date_of_birth)) { echo "value=\"" . $form_date_of_birth . "\""; }; ?>>
					</div>
				</div>
				
				<hr>
				
				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Line 1</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_line_1" placeholder="Address Line 1" maxlength="100" <?php if(!empty($form_address_line_1)) { echo "value=\"" . $form_address_line_1 . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Line 2</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_line_2" placeholder="Address Line 2" maxlength="100" <?php if(!empty($form_address_line_2)) { echo "value=\"" . $form_address_line_2 . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Town</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_town" placeholder="Address Town" maxlength="100" <?php if(!empty($form_address_town)) { echo "value=\"" . $form_address_town . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address County</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_county" placeholder="Address County" maxlength="100" <?php if(!empty($form_address_county)) { echo "value=\"" . $form_address_county . "\""; }; ?>>
					</div>
					
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Address Postcode</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" name="address_post_code" placeholder="Address Postcode" maxlength="20" <?php if(!empty($form_address_post_code)) { echo "value=\"" . $form_address_post_code . "\""; }; ?>>
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