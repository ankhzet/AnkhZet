<?php
	/** /$r_default_context = stream_context_get_default(array(
		'http' => array(
			'proxy' => 'http://localhost:8080',
			'request_fulluri' => true
		)
	));/**/

	define('CURL_TIMEOUT', 60);
	define('CURL_BOT_UA', 'AnkhZet Cache Sync Bot v0.1');

	static $curl;
	function url_get_contents($link, &$params = null) {
//		set_time_limit(0);
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
			if ($params && isset($params['referer']))
				curl_setopt($c, CURLOPT_HTTPHEADER, array('Referer' => $params['referer']));
			curl_setopt($c, CURLOPT_TIMEOUT, CURL_TIMEOUT);
			/** /
			curl_setopt($c, CURLOPT_PROXY, "http://localhost:8080");
			curl_setopt($c, CURLOPT_PROXYPORT, 8080);
			/**/
			$t = getmicrotime();

			$response = curl_exec($c);
			$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
			$params['RCode'] = $code;
			switch ($code) {
			case 200:
			case 206:
			case 301:
			case 302:
				break;
			case 404:
				$response = false;
				$params[404] = true;
				break;
			}

			$t = getmicrotime() - $t;
			$len = strlen($response);
			$kbps = fs($len / $t);
			$len = fs($len);
			$t = intval($t * 1000) / 1000;
		} catch (Exception $e) {
//			debug2($e);
			$params['exception'] = (string)$e;
			return false;
		}
//		if (1) {
			$params['speed'] = $kbps;
			$params['length'] = $len;
			$params['time'] = $t;
//		}
///		echo " &nbsp;<span style=\"color: #888; font-size: 80%;\">[{$link}] - download speed: $kbps/c ($len / $t c)</span><br />";
		return $response ? $response : false;
	}
