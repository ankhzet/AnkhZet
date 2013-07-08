<?php
	error_reporting(E_ALL);
	define('SUB_DOMEN', dirname(__FILE__));
	define('SAVE_SESSION_ACTIVITY', 0);
	define('USE_TIMELEECH', 0);
	define('VIEW_COMPILE_MSGS', 0);
	define('VIEW_COMPILE_ALWAYS', 1);
	require_once SUB_DOMEN . '/_engine/streams.php';
	require_once SUB_DOMEN . '/_engine/datetime.php';
	require_once SUB_DOMEN . '/_engine/common.php';

	set_error_handler("error_handler");

	class Application {
		var $frontend = null;

		function initialize() {
			require_once SUB_DOMEN . '/_engine/frontend.php';
			$this->frontend = FrontEnd::getInstance();
			$this->frontend->set('started', microtime());
		}

		function run() {
			$this->frontend->route();
			$this->frontend->dispatch(true);
		}
	}

	$app = new Application();
	$app->initialize();
	ob_start("ob_gzhandler");

	$app->run();
	msqlDB::o()->close();
	header('X-Frame-Options: SAMEORIGIN');
	ob_end_flush();
?>