<?php
	if (1)
		error_reporting(0);
	else
		error_reporting(E_ALL ^ E_NOTICE);

	define('ROOT', dirname(__FILE__));

	define('ENGINE_ROOT', dirname(__FILE__) . '/_engine');
	define('SUB_DOMEN', ROOT);

	define('SUB_DOMEN', dirname(__FILE__));
	define('ROOT', SUB_DOMEN);
	define('ENGINE_ROOT', ROOT . '/_engine');
	define('MODELS_ROOT', ROOT . '/models');
	define('SUB_MODELS', SUB_DOMEN . '/models');
	$include = get_include_path();
	set_include_path(join(PATH_SEPARATOR, array(ENGINE_ROOT, MODELS_ROOT, $include)));

	require_once ENGINE_ROOT . '/common.php';
	require_once ENGINE_ROOT . '/dbengine.php';
	require_once ENGINE_ROOT . '/acl.php';
	require_once ENGINE_ROOT . '/user.php';
	require_once ENGINE_ROOT . '/localization.php';

	require_once 'core_history.php';
	require_once 'core_updates.php';

	$h = HistoryAggregator::getInstance();
	$a = $h->authorsToUpdate(0, intval($r[0]));
	if (count($a)) {
		require_once 'core_updates.php';
		$u = new AuthorWorker();
		foreach ($a as $id)
			$u->check($id);
	} else
		echo Loc::lget('nothing_to_update') . '<br />';

?>