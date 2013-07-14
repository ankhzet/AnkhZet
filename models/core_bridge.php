<?php
	$r_default_context = stream_context_get_default(array(
		'http' => array(
			'proxy' => 'http://localhost:8080',
			'request_fulluri' => true
		)
	));

	define('CURL_TIMEOUT', 15);
	define('CURL_BOT_UA', 'AnkhZet Cache Sync Bot v0.1');

	function url_get_contents($link, $params = null) {
		$response = '';
		$len = 0;
		$t = 0;
		$kbps = 0;
		try {
			static $curl;
			$c = $curl ? $curl : ($curl = curl_init());
			curl_setopt($c, CURLOPT_URL, $link);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_USERAGENT, CURL_BOT_UA);
			curl_setopt($c, CURLOPT_HTTPHEADER, array('X-Bot' => CURL_BOT_UA));
			curl_setopt($c, CURLOPT_TIMEOUT, CURL_TIMEOUT);
			/**/
//			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_PROXY, "http://localhost:8080");
			curl_setopt($c, CURLOPT_PROXYPORT, 8080);
			/**/
			$t = getmicrotime();
			$response = curl_exec($c);
			$t = getmicrotime() - $t;
			$len = strlen($response);
			$kbps = fs($len / $t);
		} catch (Exception $e) {
		}
		echo " &nbsp;<span style=\"color: #888; font-size: 80%;\">[{$link}] - download speed: {$kbps}/s ($len bytes / $t sec)</span><br />";
		return $response ? $response : false;
	}
?>