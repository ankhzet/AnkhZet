<?php
	$r_default_context = stream_context_get_default(array(
		'http' => array(
			'proxy' => 'tcp://proxy.munged.edu:8080',
			'request_fulluri' => true
		)
	));

	function url_get_contents($link, $params = null) {
		ob_start();
		$response = '';
		$len = 0;
		$t = 0;
		$kbps = 0;
		try {
			$c = curl_init($link);
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
			curl_close($c);
		} catch (Exception $e) {
		}
		ob_end_clean();
		echo " &nbsp;<span style=\"color: #888; font-size: 80%;\">[{$link}] - download speed: $kbps ($len bytes / $t sec)</span><br />";
		return $r ? $response : false;
	}
?>