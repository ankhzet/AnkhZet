<?php
	$document_root  = dirname(__FILE__);
	$requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
	$requested_file = basename($requested_uri);
	$source_file    = $document_root.$requested_uri;

	if (is_file($source_file)) {
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$mime = array('css' => 'text/css', 'js' => 'application/javascript', 'htm' => 'text/html', 'html' => 'text/html');
		header("Content-Type: ".$mime[$extension]);
//		if ($c = $_SERVER['HTTP_ACCEPT_ENCODING']) {
		$c = 'gzip';
			$file = file_get_contents($source_file);
			$file = (strtolower($c) == 'gzip') ? gzcompress($file) : gzdeflate($file);
			header('Content-Length: '.strlen($file));
			header('Content-Encoding: '.$c);
			exit($file);
//		}
		header('Content-Length: '.filesize($filename));
		readfile($filename);
	}
?>