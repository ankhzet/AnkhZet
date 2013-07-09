<?php
	define('USE_TIMELEECH', 0);

	require_once 'base.php';

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