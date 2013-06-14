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
		try {
			$c = curl_init($link);
			/**/
//			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_PROXY, "http://localhost:8080");
			curl_setopt($c, CURLOPT_PROXYPORT, 8080);
			/**/
			$r = curl_exec($c);
			$response = ob_get_contents();
			curl_close($c);
		} catch (Exception $e) {
		}
		ob_end_clean();
		return $r ? $response : false;
	}
?>