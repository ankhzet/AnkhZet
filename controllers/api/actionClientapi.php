<?php

	require_once 'core_page.php';
	require_once 'core_pagecontroller_utils.php';

	class actionClientapi {
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

		function methodAuthor($params, &$error, &$message) {
			require_once 'core_authors.php';
			$id = uri_frag($params, 'id');
			$a = AuthorsAggregator::getInstance();
			return $a->get($id);
		}
		function methodAuthorsToUpdate($params, &$error, &$message) {
			$force = !!uri_frag($params, 'force');
			$all = !!uri_frag($params, 'all');

			require_once 'core_history.php';
			require_once 'core_updates.php';
			require_once 'core_authors.php';

			$u = new AuthorWorker();
			$h = HistoryAggregator::getInstance();

			$a = $h->authorsToUpdate(0, $force, $all);
			return $a;
		}
		function methodAuthorUpdate($params, &$error, &$message) {
			require_once 'core_history.php';
			require_once 'core_updates.php';
			require_once 'core_authors.php';
			$id = uri_frag($params, 'id');
			$u = new AuthorWorker();
			$checked = $u->check($id);
			if ($error = $message = $checked['error'])
				return;

			$gu = array();
			$h = HistoryAggregator::getInstance();
			$ga = GroupsAggregator::getInstance();
			$g = $h->authorGroupsToUpdate($id, true);
			foreach ($g as $gid) {
				$updatedGroup = $u->checkGroup($gid);
				if (count($updatedGroup)) {
					$d = $ga->get($gid, 'title');
					$gu[] = array('group-id' => $gid, 'group-title' => $d['title'], 'updates' => $updatedGroup);
				}
			}
			if (count($gu))
				$checked['groups-updates'] = $gu;
			return $checked;
		}
		function methodUpdatesCalcFreq($params, &$error, &$message) {
			require_once 'core_history.php';
			require_once 'core_authors.php';
			$h = HistoryAggregator::getInstance();
			$h->calcCheckFreq();
		}
		function methodLoadUpdates($params, &$error, &$message) {
			require_once 'core_updates.php';

			$u = new AuthorWorker();
			$left = $u->serveQueue(uri_frag($_REQUEST, 'left', 5));
			return $left;
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

			$aa = PagesAggregator::getInstance();
			$d = $aa->fetch($q);

			$error = !$d['result'];
			if (!$error)
				return $d['data'];
			else
				$message = 'Request failed';

			return array();
		}

		function methodPageView($params, &$error, &$message) {
			$page = uri_frag($params, 'page');

			if ($error = !$page) {
				$message = 'Page ID not specified';
				return;
			}

			$pa = PagesAggregator::getInstance();
			$d = $pa->get($page, '`id`, `title`');

			if ($error = !($d && (intval($d['id']) == $page))) {
				$message = 'Page not found';
				return;
			}

			$version = uri_frag($params, 'version', null, false);

			$version = $version ? PageUtils::decodeVersion(explode('/', $version)) : null;

			if ($error = !$version) {
				$message = 'Can\'t parse version';
				return;
			}


			$contents = PageUtils::getPageContents($page, $version);

			$encoding = strtoupper(uri_frag($params, 'encoding', 'UTF-8', false));

			if ($encoding != 'CP1251')
				$encoding = 'UTF-8';

			if ($encoding != 'CP1251')
				$contents = mb_convert_encoding($contents, $encoding, 'CP1251');

			$contents = "<html><head><title>{$d['title']}</title><style>body{font-size: 0.7em;}</style></head><body bgcolor=#EDEDED>\n$contents\n</body></html>";

			return array('mime' => 'text/html', 'encoding' => $encoding, 'title' => $d['title'], 'contents' => $contents);
		}

		function methodPageVersions($params, &$error, &$message) {
			$page = uri_frag($params, 'page');

			if ($error = !$page) {
				$message = 'Page ID not specified';
				return;
			}

			$pa = PagesAggregator::getInstance();
			$d = $pa->get($page, '`id`, `title`');

			if ($error = !($d && (intval($d['id']) == $page))) {
				$message = 'Page not found';
				return;
			}

			$storage = PageUtils::getPageStorage($page);
			$d = @dir($storage);
			$v = array();
			if ($d)
				while (($entry = $d->read()) !== false)
					if (($timestamp = intval($entry)) && is_file("$storage/$entry")) {
						$v[] = array(
							'timestamp' => $timestamp
						, 'timestr' => date('d-m-Y/H-i-s', $timestamp)
						, 'size' => filesize("$storage/$entry")
						);
					}


			return $v;
		}

		function methodPageDiff($params, &$error, &$message) {
			$page = uri_frag($params, 'page');
			if ($error = !$page) {
				$message = 'Page ID not specified';
				return;
			}
			$pa = PagesAggregator::getInstance();
			$d = $pa->get($page, '`id`, `title`');

			if ($error = !($d && (intval($d['id']) == $page))) {
				$message = 'Page not found';
				return;
			}
			$version = uri_frag($params, 'version', null, false);
			$version = $version ? PageUtils::decodeVersion(explode('/', $version), 0, true) : null;
			if ($error = !$version) {
				$message = 'Can\'t parse version';
				return;
			}

			$cur = $version[0];
			$old = $version[1];


			if ($error = !($cur * $old)) {
				$message = 'Old or current version not found';
				return;
			}

			$t1 = PageUtils::getPageContents($page, $old);
			$t2 = PageUtils::getPageContents($page, $cur);

			if ($error = !$t1) {
				$message = 'Old version not found';
				return;
			}
			if ($error = !$t2) {
				$message = 'Current version not found';
				return;
			}

			require_once 'core_diff.php';
			ob_start();
			$io = new DiffIOClean(1024);
			$io->show_new = true;
			$db = new DiffBuilder($io);
			$h = $db->diff($t1, $t2);
			$contents = ob_get_contents();
			ob_end_clean();

			$encoding = strtoupper(uri_frag($params, 'encoding', 'UTF-8', false));
			if ($encoding != 'UTF-8')
				$encoding = 'CP1251';
			if ($encoding != 'UTF-8')
				$contents = mb_convert_encoding($contents, $encoding, 'UTF-8');

			$contents = "
<html>
	<head>
		<title>{$d['title']}</title>
		<style>
			body{font-size: 0.7em;}
			ins, del{text-decoration: none;}
			ins{color:green}
			del(color:red}
			.context{color:silver}
		</style>
	</head>
<body bgcolor=#EDEDED>
$contents
</body>
</html>";

			return array('mime' => 'text/html', 'encoding' => $encoding, 'title' => $d['title'], 'contents' => $contents);
		}

	}

	function unhtmlentities ($string) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		return strtr ($string, $trans_tbl);
	}
