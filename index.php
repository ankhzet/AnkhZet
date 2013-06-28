<?php
	error_reporting(E_ALL ^ E_NOTICE);
	define('SUB_DOMEN', dirname(__FILE__));
	define('SAVE_SESSION_ACTIVITY', 0);
	define('USE_TIMELEECH', 0);
	define('VIEW_COMPILE_MSGS', 0);
	define('VIEW_COMPILE_ALWAYS', 1);

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
	ob_end_flush();
?>