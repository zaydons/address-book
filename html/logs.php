<?php
	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");
	
	// Set $page_name so that the title of each page is correct
	$page_name = PAGENAME_LOGS;
	
	// Check if $user is authenticated
	if(!$user->authenticated) {
		$user->logout('not_authenticated');
	}; // Close if(!$user->authenticated)
	
	// setting $datatables_required to 1 will ensure it is included in the <head> in layout.head.inc.php and so the <script> is called in the layout.footer.inc.php
	$datatables_required = 1;
	// Table ID to relate to the datatable, as identified in the <table> and in the <script>, needed to identify which tables to make into datatables
	$datatables_table_id = "logs";
	// Load the log entries a page at a time from logs-data.php, newest first, rather than all at once
	$datatables_option = '"serverSide": true, "processing": true, "ajax": "logs-data.php", "order": [[ 0, "desc" ]], "searchDelay": 400';
	
	// Create new Log instance, and log the page view to the database
	$log = new Log('view');
	
	// Remove log entries older than LOG_RETENTION_DAYS days
	$log->remove_old();
	
	// Require head content in the page
	require_once("../includes/layout.head.inc.php");
	// Requre navigation content in the page
	require_once("../includes/layout.navigation.inc.php");
?>
	
			<!-- CONTENT -->
			<?php $session->output_message(); ?>
			
			<table id="<?php echo $datatables_table_id; ?>" class="table table-striped table-hover" style="width: 100%">
				<thead>
					<tr>
						<th>Date</th>
						<th>Action</th>
						<th>User</th>
						<th>IP</th>
					</tr>
				</thead>
				<tbody>
					<!-- Filled in by DataTables from logs-data.php -->
				</tbody>
			</table>
			<!-- /CONTENT -->

<?php
	// Requre footer content in the page, including any relevant scripts
	require_once("../includes/layout.footer.inc.php");
?>