    <body>
		<!-- Navigation bar, which collapses into a menu button on small screens -->
		<nav class="navbar navbar-expand-md bg-body-tertiary border-bottom sticky-top">
			<div class="container">
				<a class="navbar-brand" href="<?php echo PAGELINK_INDEX; ?>">Contacts System</a>
				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle Navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div id="navbar" class="collapse navbar-collapse">
					<ul class="navbar-nav">
						<li class="nav-item"><a class="nav-link<?php if($page_name == PAGENAME_INDEX || $page_name == PAGENAME_CONTACTS) echo ' active" aria-current="page'; ?>" href="<?php echo PAGELINK_INDEX; ?>"><i class="fa fa-address-book" aria-hidden="true"></i> <?php echo PAGENAME_INDEX; ?></a></li>
						<li class="nav-item"><a class="nav-link<?php if($page_name == PAGENAME_USERS) echo ' active" aria-current="page'; ?>" href="<?php echo PAGELINK_USERS; ?>"><i class="fa fa-users" aria-hidden="true"></i> <?php echo PAGENAME_USERS; ?></a></li>
						<li class="nav-item"><a class="nav-link<?php if($page_name == PAGENAME_LOGS) echo ' active" aria-current="page'; ?>" href="<?php echo PAGELINK_LOGS; ?>"><i class="fa fa-list" aria-hidden="true"></i> <?php echo PAGENAME_LOGS; ?></a></li>
						<li class="nav-item"><a class="nav-link<?php if($page_name == PAGENAME_API) echo ' active" aria-current="page'; ?>" href="<?php echo PAGELINK_API; ?>"><i class="fa fa-plug" aria-hidden="true"></i> <?php echo PAGENAME_API; ?></a></li>
						<li class="nav-item">
							<!-- Switches between following the device setting, dark and light themes - see js/theme.js -->
							<button type="button" class="nav-link navbar-theme" title="Change theme" aria-label="Change theme">
								<span class="theme-auto"><i class="fa fa-adjust" aria-hidden="true"></i> Auto</span>
								<span class="theme-dark"><i class="fa fa-moon-o" aria-hidden="true"></i> Dark</span>
								<span class="theme-light"><i class="fa fa-sun-o" aria-hidden="true"></i> Light</span>
							</button>
						</li>
						<li class="nav-item">
							<!-- Log out is a form with a CSRF token so that other sites can't log users out with a link -->
							<form action="<?php echo PAGELINK_LOGOUT; ?>" method="post">
								<input type="hidden" name="csrf_token" value="<?php echo htmlentities(CSRF::get_token()); ?>"/>
								<button type="submit" class="nav-link navbar-logout<?php if($page_name == PAGENAME_LOGOUT) echo ' active" aria-current="page'; ?>"><i class="fa fa-sign-out" aria-hidden="true"></i> <?php echo PAGENAME_LOGOUT; ?></button>
							</form>
						</li>
					</ul>
				</div>
			</div>
		</nav>

		<div class="container pt-3">
			<h2><?php if(isset($subpage_name)) { echo $subpage_name; } else { echo $page_name; } ?></h2>

			<hr/>
