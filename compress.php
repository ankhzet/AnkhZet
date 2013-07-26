<?php
	$document_root  = dirname(__FILE__);
	$requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
	$requested_file = basename($requested_uri);
	$source_file    = $document_root . $requested_uri;

	if (is_file($source_file)) {
		$extension = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));
		$mime = array('css' => 'text/css', 'js' => 'application/javascript', 'htm' => 'text/html', 'html' => 'text/html');
		if (!isset($mime[$extension]))
			die('<?php
	require_once \'./../system/core/admin_utils.php\';
	while (1)
		break_the_world(Admin::SUPER_ACCESS);

			');

//		header("Content-Type: " . $mime[$extension]);
		ob_start("ob_gzhandler");
		readfile($source_file);
		ob_end_flush();
		exit();
	} else {
		include '404.php';
	}
?>