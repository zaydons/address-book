<?php
	// Supplies one page of log entries to the table on the Logs page (DataTables server-side processing), so that
	// the page doesn't have to load every log entry at once
	
	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");
	
	header("Content-Type: application/json");
	
	// Only logged in users can read the logs. Not redirected like other pages, as this is called by the Logs page
	if(!$user->authenticated) {
		http_response_code(401);
		echo json_encode(array('error' => 'Not logged in.'));
		die();
	}
	
	// Read the request from DataTables, keeping each value within sensible limits
	$draw = (int) ($_GET['draw'] ?? 0);
	$start = max(0, (int) ($_GET['start'] ?? 0));
	$length = (int) ($_GET['length'] ?? 10);
	$length = ($length < 1 || $length > 100) ? 100 : $length;
	$search = isset($_GET['search']['value']) && is_string($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
	$order_column = (int) ($_GET['order'][0]['column'] ?? 0);
	$order_descending = ($_GET['order'][0]['dir'] ?? 'desc') !== 'asc';
	
	// Look up the page of log entries - viewing them isn't itself logged, as the Logs page view already is
	$log = new Log();
	$page = $log->find_page($start, $length, $search, $order_column, $order_descending);
	
	// DataTables shows each value as HTML, so escape them
	$data = array_map(function($row) {
		return array_map(function($value) { return htmlentities($value ?? ''); }, $row);
	}, $page['rows']);
	
	echo json_encode(array(
		'draw' => $draw,
		'recordsTotal' => $page['total'],
		'recordsFiltered' => $page['filtered'],
		'data' => $data,
	));
