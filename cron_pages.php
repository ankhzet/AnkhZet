<?php
	require_once 'base.php';

	require_once 'core_updates.php';

	$u = new AuthorWorker();
	$left = $u->serveQueue(uri_frag($_REQUEST, 'left', 5));
	if ($left)
		locate_to('/cron_pages.php?left=' . $left);
?>