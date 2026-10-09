<?php
	// Adds contacts from a CSV file or a vCard (.vcf) file, such as one exported from a phone, Google Contacts or iCloud

	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");

	// Set $page_name so that the title of each page is correct
	$page_name = PAGENAME_CONTACTS;
	// Set $subpage_name as this page isn't the main section
	$subpage_name = PAGENAME_CONTACTSIMPORT;

	// Check if $user is authenticated
	if(!$user->authenticated) {
		$user->logout('not_authenticated');
	}; // Close if(!$user->authenticated)

	// Obtain a CSRF token to be used to prevent CSRF - this is stored in the $_SESSION
	$csrf_token = CSRF::get_token();

	// The result of an import, shown below the form
	$result = null;

	// If submit button has been pressed then process the form
	if(isset($_POST["submit"]) && $_POST["submit"] == "submit") {
		$errors = array();

		// Check that the submitted CSRF token is the same as the one in the $_SESSION to prevent cross site request forgery
		if(!CSRF::check_token($_POST['csrf_token'] ?? null))							{ $errors[] = $validation['invalid']['security']['csrf_token']; };

		// Check that a file was uploaded
		$upload = $_FILES['file'] ?? null;
		if(!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) == UPLOAD_ERR_NO_FILE) {
			$errors[] = $notification['contact']['import']['no_file'];
		} elseif(in_array($upload['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
			$errors[] = $notification['contact']['import']['too_large'];
		} elseif($upload['error'] != UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
			$errors[] = $notification['contact']['import']['failure'];
		}

		if(empty($errors)) {
			try {
				// Read the contacts from the file, then add them
				$contacts = ContactTransfer::parse(file_get_contents($upload['tmp_name']));
				$result = ContactTransfer::import($contacts, !empty($_POST['skip_duplicates']));

				// Create new Log instance, and log the action to the database
				$log = new Log('contacts_import', $result['added'] . ' added, ' . $result['duplicates'] . ' already in the address book, ' . count($result['problems']) . ' with problems, from ' . basename($upload['name']));
			} catch(InvalidArgumentException $error) {
				// The file couldn't be read, such as a CSV file without a header row
				$session->message_validation(array(htmlentities($error->getMessage())));
				$log = new Log('contacts_import', 'Failed: ' . $error->getMessage());
			}
		} else {
			$session->message_validation($errors);
			$log = new Log('contacts_import', 'Failed: no file');
		}
	} else {
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

			<?php if($result !== null) { ?>
			<div class="alert <?php echo empty($result['problems']) ? 'alert-success' : 'alert-warning'; ?>" role="alert">
				<p class="mb-0"><strong><?php echo (int) $result['added']; ?></strong> contact<?php echo $result['added'] == 1 ? '' : 's'; ?> added.<?php if($result['duplicates']) { ?> <strong><?php echo (int) $result['duplicates']; ?></strong> skipped as already in the address book.<?php }; ?></p>
				<?php if(!empty($result['problems'])) { ?>
				<p class="mt-2 mb-1"><?php echo count($result['problems']); ?> could not be added:</p>
				<ul class="mb-0">
					<?php foreach(array_slice($result['problems'], 0, 50) as $problem) { ?>
					<li><?php echo htmlentities($problem); ?></li>
					<?php }; ?>
					<?php if(count($result['problems']) > 50) { ?>
					<li>and <?php echo count($result['problems']) - 50; ?> more.</li>
					<?php }; ?>
				</ul>
				<?php }; ?>
			</div>
			<p><a href="<?php echo PAGELINK_INDEX; ?>" class="btn btn-primary">Back to the Address Book</a></p>
			<hr>
			<?php }; ?>

			<p>Add contacts from a file:</p>
			<ul>
				<li><strong>vCard (.vcf)</strong> - exported from a phone, Google Contacts, iCloud, Outlook or another address book.</li>
				<li><strong>CSV (.csv)</strong> - from a spreadsheet, with a header row naming the columns. Use the same columns as an <a href="<?php echo PAGELINK_CONTACTSEXPORT; ?>?format=csv">exported CSV file</a>: <?php echo htmlentities(implode(', ', array_keys(ContactTransfer::CSV_COLUMNS))); ?>. Dates of birth are written as YYYY-MM-DD.</li>
			</ul>
			<p>Each contact needs a name. Phone numbers, email addresses and dates of birth are checked, and any contacts with problems are listed so that they can be added by hand.</p>

			<form action="" method="post" enctype="multipart/form-data">
				<div class="mb-3">
					<label for="file" class="form-label fw-bold">File</label>
					<input type="file" class="form-control" name="file" id="file" accept=".csv,.vcf,.vcard,text/csv,text/vcard" required>
				</div>

				<div class="form-check mb-3">
					<input class="form-check-input" type="checkbox" name="skip_duplicates" id="skip_duplicates" value="1" checked>
					<label class="form-check-label" for="skip_duplicates">Skip contacts which are already in the address book (the same name, phone numbers and email address)</label>
				</div>

				<input type="hidden" name="csrf_token" value="<?php echo htmlentities($csrf_token); ?>"/>

				<button type="submit" name="submit" value="submit" class="btn btn-primary">Import</button>
			</form>
			<!-- /CONTENT -->

<?php
	// Requre footer content in the page, including any relevant scripts
	require_once("../includes/layout.footer.inc.php");
?>
