<?php
	// Downloads all contacts as a CSV file (?format=csv), for spreadsheets, or a vCard file (?format=vcf), for phones and other address books
	
	// Require relevent information for settings.config.inc.php, including functions and database access
	require_once("../includes/settings.config.inc.php");
	
	// Check if $user is authenticated
	if(!$user->authenticated) {
		$user->logout('not_authenticated');
	}; // Close if(!$user->authenticated)
	
	$format = ($_GET['format'] ?? 'csv') === 'vcf' ? 'vcf' : 'csv';
	
	// Obtain all contacts, in name order
	$contacts = DB::get_instance()->query('SELECT * FROM contacts ORDER BY last_name, first_name')->fetchAll(PDO::FETCH_ASSOC);
	
	// Create new Log instance, and log the action to the database
	$log = new Log('contacts_export', count($contacts) . ' contacts as ' . ($format == 'vcf' ? 'vCard' : 'CSV'));
	
	// Send the file as a download
	$filename = 'address-book-' . date('Y-m-d') . '.' . $format;
	if($format == 'vcf') {
		header('Content-Type: text/vcard; charset=utf-8');
		$file = ContactTransfer::to_vcard($contacts);
	} else {
		header('Content-Type: text/csv; charset=utf-8');
		$file = ContactTransfer::to_csv($contacts);
	}
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: no-store');
	echo $file;
