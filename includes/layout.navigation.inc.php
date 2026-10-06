
    <body>
		<!-- Fixed navbar -->
		<nav class="navbar navbar-default navbar-fixed-top">
			<div class="container">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
						<span class="sr-only">Toggle Navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="<?php echo PAGELINK_INDEX; ?>">Contacts System</a>
				</div>
				<div id="navbar" class="navbar-collapse collapse">
					<ul class="nav navbar-nav">
						<li <?php if($page_name == PAGENAME_INDEX || $page_name == PAGENAME_CONTACTS) echo 'class="active"'; ?>><a href="<?php echo PAGELINK_INDEX; ?>"><i class="fa fa-address-book" aria-hidden="true"></i> <?php echo PAGENAME_INDEX; ?></a></li>
						<li <?php if($page_name == PAGENAME_USERS) echo 'class="active"'; ?>><a href="<?php echo PAGELINK_USERS; ?>"><i class="fa fa-users" aria-hidden="true"></i> <?php echo PAGENAME_USERS; ?></a></li>
						<li <?php if($page_name == PAGENAME_LOGS) echo 'class="active"'; ?>><a href="<?php echo PAGELINK_LOGS; ?>"><i class="fa fa-list" aria-hidden="true"></i> <?php echo PAGENAME_LOGS; ?></a></li>
						<li <?php if($page_name == PAGENAME_API) echo 'class="active"'; ?>><a href="<?php echo PAGELINK_API; ?>"><i class="fa fa-plug" aria-hidden="true"></i> <?php echo PAGENAME_API; ?></a></li>
						<li <?php if($page_name == PAGENAME_LOGOUT) echo 'class="active"'; ?>>
							<!-- Log out is a form with a CSRF token so that other sites can't log users out with a link -->
							<form action="<?php echo PAGELINK_LOGOUT; ?>" method="post">
								<input type="hidden" name="csrf_token" value="<?php echo htmlentities(CSRF::get_token()); ?>"/>
								<button type="submit" class="btn btn-link navbar-logout"><i class="fa fa-sign-out" aria-hidden="true"></i> <?php echo PAGENAME_LOGOUT; ?></button>
							</form>
						</li>
					</ul>
				</div><!--/.nav-collapse -->
			</div>
		</nav>
		
		<div class="container pt-50">
			<h2><?php if(isset($subpage_name)) { echo $subpage_name; } else { echo $page_name; } ?></h2>
			
			<hr/>
