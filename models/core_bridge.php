<?php
	$r_default_context = stream_context_get_default(array(
		'http' => array(
			'proxy' => 'http://localhost:8080',
			'request_fulluri' => true
		)
	));

	function url_get_contents($link, $params = null) {
		$response = '';
		$len = 0;
		$t = 0;
		$kbps = 0;
		ob_start();
		try {
			static $curl;
			$c = $curl ? $curl : ($curl = curl_init());
			curl_setopt($c, CURLOPT_URL, $link);
			/**/
//			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_PROXY, "http://localhost:8080");
			curl_setopt($c, CURLOPT_PROXYPORT, 8080);
			/**/
			$t = getmicrotime();
			$r = curl_exec($c);
			$t = getmicrotime() - $t;
			$response = ob_get_contents();
			$len = strlen($response);
			$kbps = fs($len / $t);
		} catch (Exception $e) {
		}
		ob_end_clean();
		echo " &nbsp;<span style=\"color: #888; font-size: 80%;\">[{$link}] - download speed: {$kbps}/s ($len bytes / $t sec)</span><br />";
		return $r ? $response : false;
	}
?>