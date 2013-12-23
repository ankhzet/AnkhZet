<?php

	class actionClientapi {
		function execute($params) {
			$action = strtolower(uri_frag($params, 'api-action', null, false));
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

		function methodUpdates($params, &$error, &$message) {
			require_once 'core_updatesfetcher.php';
			$fetch = UpdatesFetcher::aquireData($params, $title_opts);
			$error = is_string($fetch);
			$message = $error ? $fetch : '';

			switch (post('response_type')) {
			case 'api':
			case 'json':
				break;
			default:
				if (!$error) {
					if (isset($title_opts)) {
						$t = array_merge(array('title' => 'AnkhZet API'), array('sub-channel' => unhtmlentities($title_opts['title'])));
						$message = patternize(Loc::lget('rss-title' . $title_opts['channel']), $t);
					} else
						$message = '';
				}
			}

			return $fetch;
		}

		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}

		function methodUser($params, &$error, &$message) {
			$uid = uri_frag($params, 'uid');
			$u = array();
			$fields = array(
				User::COL_LOGIN,
				User::COL_ACL,
				User::COL_LANG,
				User::COL_NAME
			);

			$error = !($uid && ($data = User::get($uid)) && ($data->ID()));
			if (!$error) {
				foreach ($fields as $field)
					$u[$field] = $data->_get($field);


				$u[User::COL_LANG] = Loc::$LOC_ALL[$u[User::COL_LANG]];

				return $u;
			} else
				$message = 'User with specified ID not found';
		}

		function methodAuthors($params, &$error, &$message) {
			$c = uri_frag($params, 'collumns', null, false);
			$c = $c ? explode(',', $c) : null;
			foreach ($c as &$collumn)
				$collumn = preg_replace('/[^\w\d_]/i', '', $collumn);

			$collumns = $c ? '`' . join('`,`', $c) . '`' : '*';

			require_once 'core_authors.php';
			$aa = AuthorsAggregator::getInstance();
			$d = $aa->fetch(array('nocalc' => true, 'pagesize' => 10000, 'collumns' => $collumns));

			$error = !$d['result'];
			if (!$error)
				return $d['data'];
			else
				$message = 'Request failed';
		}

		function methodGroups($params, &$error, &$message) {
			$c = uri_frag($params, 'collumns', null, false);
			$c = $c ? explode(',', $c) : null;
			foreach ($c as &$collumn)
				$collumn = preg_replace('/[^\w\d_]/i', '', $collumn);

			$collumns = $c ? '`' . join('`,`', $c) . '`' : '*';

			$author = uri_frag($params, 'author', 0);
			$q = array('nocalc' => true, 'pagesize' => 10000, 'collumns' => $collumns);
			if ($author)
				$q['filter'] = "`author` = $author";

			require_once 'core_authors.php';
			$aa = GroupsAggregator::getInstance();
			$d = $aa->fetch($q);

			$error = !$d['result'];
			if (!$error)
				return $d['data'];
			else
				$message = 'Request failed';

			return array();
		}
		function methodPages($params, &$error, &$message) {
			$c = uri_frag($params, 'collumns', null, false);
			$c = $c ? explode(',', $c) : null;
			foreach ($c as &$collumn)
				$collumn = preg_replace('/[^\w\d_]/i', '', $collumn);

			$collumns = $c ? '`' . join('`,`', $c) . '`' : '*';

			$author = uri_frag($params, 'author', 0);
			$q = array('nocalc' => true, 'pagesize' => 10000, 'collumns' => $collumns);
			if ($author)
				$q['filter'] = "`author` = $author";

			require_once 'core_page.php';
			$aa = PagesAggregator::getInstance();
			$d = $aa->fetch($q);

			$error = !$d['result'];
			if (!$error)
				return $d['data'];
			else
				$message = 'Request failed';

			return array();
		}
	}

	function unhtmlentities ($string) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		return strtr ($string, $trans_tbl);
	}
