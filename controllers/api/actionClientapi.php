<?php

	class actionClientapi {
		function execute($params) {
			$action = uri_frag($params, 'action', null, false);
			$method = 'method' . ucfirst(strtolower($action));
			if (method_exists($this, $method)) {
				unset($params['action']);
				return $this->{$method}($params);
			} else
				$this->_404('Action not found');

			return true;
		}

		function methodUpdates($params) {
			require_once 'core_updatesfetcher.php';
			$fetch = UpdatesFetcher::aquireData($params, $title_opts);
			$error = is_string($fetch);

			switch (post('response_type')) {
			case 'json':
				require_once 'json.php';
				$fetch = JSON_Result($error ? JSON_Fail : JSON_Ok, $fetch, false);
				$content_type = "json";
				$file = "updates.json";
				break;
			default:
				require_once 'core_rss.php';
				$xml = XMLWorker::get();

				if (!$error) {
					if (isset($title_opts)) {
						$t = array_merge(array('title' => 'AnkhZet API'), array('sub-channel' => unhtmlentities($title_opts['title'])));
						$message = patternize(Loc::lget('rss-title' . $title_opts['channel']), $t);
					} else
						$message = '';

					$data = array('result' => JSON_Ok, 'data' => $fetch, 'message' => $message);
				} else
					$data = array('result' => JSON_Fail, 'data' => array(), 'message' => $fetch);

				$fetch = $xml->format($data);
				$content_type = "rss+xml";
				$file = "updates.xml";
			}

			$gzip = !post_int('nogzip');
			$debug = post_int('debug');
			if ($gzip) header('Content-Encoding: gzip');


//			if (!$debug) header("Content-Type: application/$content_type; charset=UTF-8");
			if (!$gzip) header('Content-Length: ' . strlen($fetch));
//			if (!$debug) header("Content-Disposition: inline; filename=$file");

			header('Cache-Control: no-store; no-cache');

			if ($gzip) $fetch = gzencode($fetch);

			echo $fetch;

			die();
		}

		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}

		function methodUser($params) {
			$uid = uri_frag($params, 'uid');
			$u = array();
			$fields = array(
				User::COL_LOGIN,
				User::COL_ACL,
				User::COL_LANG,
				User::COL_NAME
			);

			if ($uid && ($data = User::get($uid)) && ($data->ID())) {
				foreach ($fields as $field)
					$u[$field] = $data->_get($field);


				$u[User::COL_LANG] = Loc::$LOC_ALL[$u[User::COL_LANG]];

				Config_Result('user', JSON_Ok, $u);
			} else
				Config_Result('user', JSON_Fail, 'User with specified ID not found');

		}
	}

	function unhtmlentities ($string) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		return strtr ($string, $trans_tbl);
	}
