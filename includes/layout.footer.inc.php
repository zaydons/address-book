		</div>
		<?php
		if(isset($datatables_required) && $datatables_required == 1) {
			$datatable_script = <<<FILEDOC
			
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			new DataTable('#{$datatables_table_id}', {
				{$datatables_option}
			});
		});
		</script>
		
FILEDOC;
			echo $datatable_script;
		};
		?>
		
    </body>
	
</html>