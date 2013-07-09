<?php
	if (0)
		error_reporting(0);
	else
		error_reporting(E_ALL ^ E_NOTICE);

	define('SAVE_SESSION_ACTIVITY', 0);

	if (!defined('USE_TIMELEECH'))
		define('USE_TIMELEECH', 0);

	define('VIEW_COMPILE_MSGS', 0);
	define('VIEW_COMPILE_ALWAYS', 1);

	define('ROOT', dirname(__FILE__));
	define('ENGINE_ROOT', ROOT . '/_engine');
	define('SUB_DOMEN', ROOT);
	define('MODELS_ROOT', ROOT . '/models');
	define('SUB_MODELS', SUB_DOMEN . '/models');
	$include = get_include_path();
	set_include_path(join(PATH_SEPARATOR, array(ENGINE_ROOT, SUB_MODELS, MODELS_ROOT, $include)));


	require_once ENGINE_ROOT . '/streams.php';
	require_once ENGINE_ROOT . '/common.php';
	require_once ENGINE_ROOT . '/config.php';
	require_once ENGINE_ROOT . '/dbengine.php';
	require_once ENGINE_ROOT . '/acl.php';
	require_once ENGINE_ROOT . '/user.php';
	require_once ENGINE_ROOT . '/localization.php';
	$config = Config::read('INI', 'cms://config/config.ini');
	$timezone = $config->get('main.timezone');
	date_default_timezone_set($timezone);

	set_error_handler("error_handler");
