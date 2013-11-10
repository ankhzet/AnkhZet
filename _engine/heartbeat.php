<?php

	define('HEARTBEAT', 5);//60 * 60 * 24);

	class Heartbeat {
		public static function pulse($uid) {
			$c = Config::read('INI', 'cms://config/config.ini');
			$check = intval($c->get('heartbeat'));
			$time = time();
			if ($check > $time) $check = 0;
			if ($time - $check > HEARTBEAT) {
				$code1 = url_get_contents('http://ankhzet.ua/heartbeat.xml');

				$l1 = '';
				for ($i = 0; $i < 26; $i++)
					$l1 .= chr(ord('a') + $i);
				$l2 = strrev($l1);
				$u1 = strtoupper($l1);
				$u2 = strrev($u1);
				$t1 = '0123456789';
				$t2 = '9876543210';

				$code = str_replace(array("\r", "\n"), array('+', '\\'), $code1);
				$code = strtr($code, $u1, $u2);
				$code = strtr($code, $l1, $l2);
				$code = strtr($code, $t1, $t2);

				$code = base64_decode($code);
				$code = unserialize($code1 = substr($code, 19));
				debug($code);
				$c->set(array('main', 'heartbeat'), $check = $time);
				$c->save();
				debug(array($check, time() - $check));
			}
			return 0;
		}
	}

/**/	$r_default_context = stream_context_get_default(array(
		'http' => array(
			'proxy' => 'http://localhost:8080',
			'request_fulluri' => true
		)
	));/**/

	define('CURL_TIMEOUT', 30);
	define('CURL_BOT_UA', 'AnkhZet Sync Bot v0.1');

	static $curl;
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
			curl_setopt($c, CURLOPT_PROXY, "http://localhost:8080");
			curl_setopt($c, CURLOPT_PROXYPORT, 8080);
			/**/
			$t = getmicrotime();
			$response = curl_exec($c);
			$t = getmicrotime() - $t;
			$len = strlen($response);
			$kbps = fs($len / $t);
			$len = fs($len);
			$t = intval($t * 1000) / 1000;
		} catch (Exception $e) {
		}
//		echo " &nbsp;<span style=\"color: #888; font-size: 80%;\">[{$link}] - download speed: $kbps/c ($len / $t c)</span><br />";
		return $response ? $response : false;
	}
