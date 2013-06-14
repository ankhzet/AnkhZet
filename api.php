<?php
	if (0)
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
	set_include_path(join(PATH_SEPARATOR, array(ENGINE_ROOT, SUB_MODELS, MODELS_ROOT, $include)));


	require_once ENGINE_ROOT . '/common.php';
	require_once ENGINE_ROOT . '/dbengine.php';
	require_once ENGINE_ROOT . '/acl.php';
	require_once ENGINE_ROOT . '/user.php';
	require_once ENGINE_ROOT . '/api.php';
	$action = strtolower($_REQUEST['action']);
	$acl = User::ACL();
	$moder = $acl >= ACL::ACL_MODER;
	$aacl = API::getACLs();
	$deny = isset($aacl[$action]) ? $aacl[$action] > $acl : !$moder;
	if ($deny) {
		set_include_path($include);
		require_once '404.php';
		exit(PHP_EOL . '<!-- ;) -->');
	}


	if (API::handle($action, $_REQUEST) === false)
		die('METHOD_UNKNOWN');
?>