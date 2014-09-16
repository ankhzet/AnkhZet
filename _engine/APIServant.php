<?php

	class APIServant {
		function execute($params) {
			$action = strtolower(uri_frag($params, 'api-action', null, false));

			if (preg_match_all('/-(\w)/i', $action, $m))
				foreach ($m[1] as $part)
					$action = str_replace("-{$part}", strtoupper($part), $action);

			$method = 'method' . ucfirst($action);
			if (method_exists($this, $method)) {
				unset($params['action']);
				$error = false;
				$message = '';
				$result = $this->{$method}($params, $error, $message);

				$failed = $error ? JSON_Fail : JSON_Ok;

				$content_type = post('response_type') ? post('response_type') : 'xml';
				$file = "$action.$content_type";

				switch ($content_type) {
				case 'api':
					$result = Config_Result($action, $failed, $error ? $message : $result, false);
					$content_type = "text/ankh-api-response";
					break;
				case 'json':
					require_once 'json.php';
					$result = JSON_Result($failed, $error ? $message : $result, false);
					break;
				default:
					require_once 'core_rss.php';
					$xml = XMLWorker::get();

					$data = array(
						'result' => $failed
					, 'data' => $error ? array() : $result
					, 'message' => $message
					);

					$result = $xml->format($data);
					$content_type = "rss+xml";
				}
			} else
				$this->_404('Action not found');

			$gzip = !post_int('nogzip');
			$debug = post_int('debug');
			if ($gzip) header('Content-Encoding: gzip');


//			if (!$debug) header("Content-Type: application/$content_type; charset=UTF-8");
			if (!$gzip) header('Content-Length: ' . strlen($result));
//			if (!$debug) header("Content-Disposition: inline; filename=$file");

			header('Cache-Control: no-store; no-cache');

			if ($gzip) $result = gzencode($result);

			die($result);

			return true;
		}

		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}

	}

	function unhtmlentities ($string) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		return strtr ($string, $trans_tbl);
	}
