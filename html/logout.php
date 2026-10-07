<?php
	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");
	
	// Set $page_name so that the title of each page is correct
	$page_name = PAGENAME_LOGOUT;
	
	// Check if $user is authenticated
	if(!$user->authenticated) {
		$user->logout('not_authenticated');
	};
	
	// Obtain a CSRF token to be used to prevent CSRF - this is stored in the $_SESSION
	$csrf_token = CSRF::get_token();
	
	// Only log out from a submitted form with a valid CSRF token, so that other sites can't log users out with a link
	if($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::check_token($_POST['csrf_token'] ?? null)) {
		// Create new Log instance, and log the action to the database
		$log = new Log('logout_success');
		
		// Log the user out
		$user->logout();
	};
	
	// Otherwise ask the user to confirm that they want to log out
	// Create new Log instance, and log the page view to the database
	$log = new Log('view');
	
	// Require head content in the page
	require_once("../includes/layout.head.inc.php");
	// Requre navigation content in the page
	require_once("../includes/layout.navigation.inc.php");
?>
	
			<!-- CONTENT -->
			<?php $session->output_message(); ?>
			
			<form action="<?php echo PAGELINK_LOGOUT; ?>" method="post">
				<p>Are you sure you want to log out?</p>
				<input type="hidden" name="csrf_token" value="<?php echo htmlentities($csrf_token); ?>"/>
				<button type="submit" class="btn btn-primary">Log Out</button>
			</form>
			<!-- /CONTENT -->

<?php
	// Requre footer content in the page, including any relevant scripts
	require_once("../includes/layout.footer.inc.php");
?>
