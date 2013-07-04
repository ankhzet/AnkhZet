<?php
	error_reporting(E_ALL);
	define('SUB_DOMEN', dirname(__FILE__));
	define('SAVE_SESSION_ACTIVITY', 0);
	define('USE_TIMELEECH', 0);
	define('VIEW_COMPILE_MSGS', 0);
	define('VIEW_COMPILE_ALWAYS', 1);
	require_once SUB_DOMEN . '/_engine/streams.php';
	require_once SUB_DOMEN . '/_engine/datetime.php';

	function error_handler($code, $msg, $file, $line) {
		$severity = array(
			E_USER_ERROR => 'ERROR'
		, E_USER_WARNING => 'WARNING'
		, E_USER_NOTICE => 'NOTICE'
		, E_WARNING => 'WARNING'
		, E_NOTICE => 'NOTICE'
		);
		$severity = @$severity[$code] ? $severity[$code] : 'ERROR';
		$date = gmdate('d-m-Y');
		$time = gmdate('h:i:s');
		$file = str_replace(array(SUB_DOMEN, '\\'), array('', '/'), $file);
		$line = "\n[{$time}] {$severity} at {$file}:{$line}:\n\t\t\t\t\t\t\t{$msg}\n";
		$log_file = "cms://logs/error-log-{$date}.php";

		if (!is_file($log_file) || !filesize($log_file)) $line = "<?php ?><pre>\n{$line}";
		if ($f = fopen($log_file, 'a')) {
			fwrite($f, $line);
			fclose($f);
		}
	}
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