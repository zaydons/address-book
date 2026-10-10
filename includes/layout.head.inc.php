<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo page_name(); ?></title>

		<!-- Light/dark theme, applied before anything else so that the page isn't shown in the wrong theme first -->
		<script src="js/theme.js"></script>

		<!-- Bootstrap (the bundle includes Popper) -->
		<link rel="stylesheet" href="assets/bootstrap/5.3.8/css/bootstrap.min.css">
		<script src="assets/bootstrap/5.3.8/js/bootstrap.bundle.min.js"></script>
<?php
		if(isset($datatables_required) && $datatables_required == 1) {
			$datatables_source = <<<FILEDOC

		<!-- DataTables, styled for Bootstrap 5 -->
		<link rel="stylesheet" href="assets/DataTables/3.1.3/css/dataTables.bootstrap5.min.css">
		<script src="assets/DataTables/3.1.3/js/dataTables.min.js"></script>
		<script src="assets/DataTables/3.1.3/js/dataTables.bootstrap5.min.js"></script>

FILEDOC;
			echo $datatables_source;
		};
?>

		<!-- Main CSS -->
		<link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">

	</head>
