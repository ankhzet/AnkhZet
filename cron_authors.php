<?php
	require_once 'base.php';

	require_once 'core_history.php';
	$h = HistoryAggregator::getInstance();
	$a = $h->authorsToUpdate(0, uri_frag($_REQUEST, 'force'));
	if (count($a)) {
		echo "Authors to update: " . (count($a)) . "<br />";
		require_once 'core_updates.php';
		$u = new AuthorWorker();
		foreach ($a as $id)
			$u->check($id);
	} else
		echo Loc::lget('nothing_to_update') . '<br />';

?>