<?php
	require_once 'base.php';

	require_once 'core_updates.php';

	$u = new AuthorWorker();
	$left = $u->serveQueue(5);
	if ($left)
		locate_to('/authors/update/' . $left);
?>