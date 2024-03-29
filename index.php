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
			$showOffline = (User::ACL() < ACL::ACL_ADMINS) && $this->frontend->get('config')->get('main.offline');
			if ($showOffline) {
				$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
				$showOffline = strpos($uri, '/user/login') === false;
			}

			if ($showOffline) {
				require_once 'view.php';
				$v = new View();
				$v->renderTPL('offline');
			} else {
				$this->frontend->route();
				$this->frontend->dispatch(true);
			}
		}
	}

	$app = new Application();
	$app->initialize();
	ob_start("ob_gzhandler");

	$app->run();
	msqlDB::o()->close();
	header('Content-Type: text/html');
	header('X-Frame-Options: SAMEORIGIN');
	ob_end_flush();
?>