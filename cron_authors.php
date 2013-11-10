<?php
	require_once 'base.php';

	require_once 'core_history.php';
	require_once 'core_updates.php';
	require_once 'core_authors.php';

	$u = new AuthorWorker();

	$h = HistoryAggregator::getInstance();
	$a = $h->authorsToUpdate(0, uri_frag($_REQUEST, 'force'));
	if (!!$a) {
		echo "Authors to update: " . (count($a)) . "<br />";
		foreach ($a as $id)
			$u->check($id);
	}

	$g = $h->groupsToUpdate(uri_frag($_REQUEST, 0));
	if (!!$g) {
		echo "Groups to update: " . (count($g)) . "<br />";
		foreach ($g as $id)
			$u->checkGroup($id);
	}

	if (!$a && !$g)
		echo Loc::lget('nothing_to_update') . '<br />';

	$h->calcCheckFreq();