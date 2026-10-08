<?php
	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");

	// Set $page_name so that the title of each page is correct
	$page_name = PAGENAME_CHANGEPASSWORD;

	// Check if $user is authenticated
	if(!$user->authenticated) {
		$user->logout('not_authenticated');
	}; // Close if(!$user->authenticated)

	// Obtain a CSRF token to be used to prevent CSRF - this is stored in the $_SESSION
	$csrf_token = CSRF::get_token();

	// If submit button has been pressed then process the form
	if(isset($_POST["submit"]) && $_POST["submit"] == "submit") {

		// Validate all fields and ensure that required fields are submitted

		// Initialise the $errors array where errors will be sent and then retrieved from
		$errors = array();

		// Required fields, if a field is not present or empty then populate the $errors array
		if(!isset($_POST["current_password"]) 	|| empty($_POST["current_password"])) 	{ $errors[] = $validation["field_required"]["user"]["current_password"]; };
		if(!isset($_POST["password"]) 			|| empty($_POST["password"])) 			{ $errors[] = $validation["field_required"]["user"]["password"]; };
		if(!isset($_POST["confirm_password"]) 	|| empty($_POST["confirm_password"])) 	{ $errors[] = $validation["field_required"]["user"]["confirm_password"]; };

		// Check that the submitted CSRF token is the same as the one in the $_SESSION to prevent cross site request forgery
		if(!CSRF::check_token($_POST['csrf_token'] ?? null))							{ $errors[] = $validation['invalid']['security']['csrf_token']; };

		// Password validation, such as length and complexity rules
		$errors = array_merge($errors, $user->password_errors($_POST["password"] ?? "", $_POST["confirm_password"] ?? ""));

		// If no errors have been found during the field validations
		if(empty($errors)) {

			if(!password_verify($_POST['current_password'], $user->details['hashed_password'])) {
				// The current password is wrong
				$session->message_validation(array($validation["password"]["current_incorrect"]));
				// Create new Log instance, and log the action to the database
				$log = new Log('password_change_failed', 'Current password was incorrect.');
			} elseif(password_verify($_POST['password'], $user->details['hashed_password'])) {
				// The new password is the same as the current password
				$session->message_alert($notification["password_change"]["same"], "danger");
				// Create new Log instance, and log the action to the database
				$log = new Log('password_change_failed', 'New password was the same as the current password.');
			} else {
				// Set the new password and clear the requirement to change it
				$update_values = array(
					'hashed_password' => $user->password_encrypt($_POST['password']),
					'must_change_password' => 0
				);
				$result = $user->update($update_values, $user->details['user_id']);

				// Test whether the query was successful
				if($result) {
					// Create new Log instance, and log the action to the database
					$log = new Log('password_change_success');
					// Set session message
					$session->message_alert($notification["password_change"]["success"], "success");
					// Redirect the user
					Redirect::to(PAGELINK_INDEX);
				} else {
					// Set session message
					$session->message_alert($notification["password_change"]["failure"], "danger");
					// Create new Log instance, and log the action to the database
					$log = new Log('password_change_failed', 'database');
				};
			};

		} else {
			// Form field validation has failed - $errors array is not empty
			// If there are any error messages in the $errors array then display them to the screen
			$session->message_validation($errors);
			// Create new Log instance, and log the action to the database
			$log = new Log('password_change_failed', 'Failed password change due to form validation errors.');
		};

	} else {
		// Form has not been submitted
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

			<?php if(!empty($user->details['must_change_password'])) { ?>
			<div class="alert alert-info" role="alert"><?php echo htmlentities($notification["password_change"]["required"]); ?></div>
			<?php }; ?>

			<form action="" method="post">

				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Current Password</label>
					<div class="col-sm-10">
						<input type="password" class="form-control" name="current_password" placeholder="Current Password" autocomplete="current-password" required>
					</div>
				</div>

				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">New Password</label>
					<div class="col-sm-10">
						<input type="password" class="form-control" name="password" placeholder="New Password" autocomplete="new-password" required>
					</div>
				</div>

				<div class="row gy-2 mb-3">
					<label class="col-sm-2 col-form-label text-sm-end fw-bold">Confirm New Password</label>
					<div class="col-sm-10">
						<input type="password" class="form-control" name="confirm_password" placeholder="Confirm New Password" autocomplete="new-password" required>
					</div>
				</div>

				<div class="row gy-2 mb-3">
					<div class="offset-sm-2 col-sm-10">
						<p>Your password must be at least 8 characters long, and contain at least 1 lower case character (a-z), 1 upper case character (A-Z) and 1 number (0-9).</p>
					</div>
				</div>

				<input type="hidden" name="csrf_token" value="<?php echo htmlentities($csrf_token); ?>"/>

				<div class="row gy-2 mb-3">
					<div class="offset-sm-2 col-sm-10">
						<button type="submit" name="submit" value="submit" class="btn btn-primary">Change Password</button>
					</div>
				</div>
			</form>
			<!-- /CONTENT -->

<?php
	// Requre footer content in the page, including any relevant scripts
	require_once("../includes/layout.footer.inc.php");
?>
