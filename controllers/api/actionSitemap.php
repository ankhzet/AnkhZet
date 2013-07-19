<?php
	class actionSitemap {
		function execute($params) {
//			$c = Config::read('INI', 'cms://config/config.ini');
			$a = array('host' => 'http://' . $_SERVER['HTTP_HOST']);
			$c = @file_get_contents('cms://root/views/sitemap.xml');
			$c = patternize($c, $a);

			header('Content-Type: application/xml; charset=UTF-8');
			header('Content-Length: ' . strlen($c));
			header('Content-Disposition: inline; filename=sitemap.xml');
			die($c);
			return true;
		}
		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}
	}

