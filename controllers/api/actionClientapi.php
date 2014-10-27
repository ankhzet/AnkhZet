<?php

	require_once 'core_page.php';
	require_once 'core_authors.php';
	require_once 'core_pagecontroller_utils.php';

	require_once 'APIServant.php';

	define('DESC_COLLUMN', 'description');

	class actionClientapi extends APIServant {

		function methodUserLogin($params, &$error, &$message) {
			$login = uri_frag($params, 'login', false, false);
			$password = uri_frag($params, 'pass', false, false);
			$uid = User::tryToLogin($login, $password);

			if ($error = !($login && $password))
				return $message = 'Login and password must be specified';

			if ($error = ($login && $password) && !intval($uid)) {
				sleep(1);
				return $message = 'Login failed: wrong login or password';
			}

			return array('uid' => $uid, 'name' => User::get($uid)->readable());
		}

		function methodUser($params, &$error, &$message) {
			$uid = uri_frag($params, 'uid');

			if ($error = !$uid)
				return $message = 'User ID must be specified';

			$user = $uid ? User::get($uid) : false;

			if ($error = !($user && $user->valid()))
				return $message = "User with specified id ($uid) not found";

			$fields = array('id', 'login', 'acl', 'lang', 'name');
			$v = array();
			foreach ($fields as $f)
				$v[$f] = $user->_get($f);

			return $v;
		}

		function methodAuthor($params, &$error, &$message) {
			return AuthorsAggregator::getInstance()->get(uri_frag($params, 'id'));
		}

		function methodUpdates($params, &$error, &$message) {
			require_once 'core_updatesfetcher.php';
			$fetch = UpdatesFetcher::aquireData($params, $title_opts);
			$error = is_string($fetch);
			$message = $error ? $fetch : '';

			if ((!$error) && (trim($filter = uri_frag($params, 'collumns', '', false)) != '')) {
				$filter = explode(',', ",$filter");
				$filter = array_flip($filter);
				$hasAny = isset($fetch[0]) ? $fetch[0] : false;
				if ($hasAny) {
					$needDesc = isset($filter[DESC_COLLUMN]);
					$glob = array();
					$desc = array();
					foreach ($hasAny as $key => &$val) {
						if (array_key_exists($key, $filter)) {

							if ($needDesc && ($key == DESC_COLLUMN)) {
								foreach ($val as $key2 => &$val2)
									if (array_key_exists($key2, $filter) && !array_key_exists($key2, $glob))
										$desc[$key2] = count($desc) + 1;

							} else
								$glob[$key] = count($glob) + 1;
						}
					}

					foreach ($fetch as &$row) {
						$filtered = array();
						foreach ($glob as $collumn => $idx)
							$filtered[$collumn] = $row[$collumn];

						if ($needDesc) {
							$filtered2 = array();
							$d = &$row[DESC_COLLUMN];
							foreach ($desc as $collumn => $idx)
								$filtered2[$collumn] = $d[$collumn];

							$filtered[DESC_COLLUMN] = $filtered2;
						}

						$row = $filtered;
					}
				}
			}
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

		function methodAuthors($params, &$error, &$message) {
			$ids = uri_frag($params, 'id', null, false);
			$ids = $ids ? explode(',', $ids) : array();
			foreach ($ids as &$id)
				$id = intval($id);

			$c = uri_frag($params, 'collumns', null, false);
			$c = $c ? explode(',', $c) : null;
			if ($c)
				foreach ($c as &$collumn)
					$collumn = preg_replace('/[^\w\d_]/i', '', $collumn);

			$collumns = $c ? '`' . join('`,`', $c) . '`' : '*';

			$aa = AuthorsAggregator::getInstance();
			$f = array('nocalc' => true, 'pagesize' => 10000, 'collumns' => $collumns);
			if (count($ids))
				$f['filter'] = '`id` in (' . join(',', $ids) . ')';
			$d = $aa->fetch($f);

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

			$id = uri_frag($params, 'id', 0);
			$author = uri_frag($params, 'author', 0);
			$group = uri_frag($params, 'group', 0);
			$q = array('nocalc' => true, 'pagesize' => 10000, 'collumns' => $collumns);

			$l = array();
			if ($id) $l[] = "`id` = $id";
			if ($author) $l[] = "`author` = $author";
			if ($group) $l[] = "`group` = $group";

			if (!!$l)
				$q['filter'] = join(' and ', $l);

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

			function pickOrigSize($filename) {
				URIStream::real($filename, $filename);
				$size = filesize($filename);
/*				$h = @fopen($filename, 'rb');
				if ($h) {
					fseek($h, -4, SEEK_END);
					$b = fread($h, 4);
					$zipped = unpack('Vsize', $b);
					$zipped = intval($zipped['size']);
					fclose($h);
				} else*/
					$zipped = strlen(gzuncompress(file_get_contents($filename)));

				$size = array($size, $zipped);
//				debug2($size);
				return $size;
			}

			$storage = PageUtils::getPageStorage($page);
			$d = @dir($storage);
			$v = array();
			if ($d)
				while (($entry = $d->read()) !== false)
					if (($timestamp = intval($entry)) && is_file("$storage/$entry")) {
						$size = pickOrigSize("$storage/$entry");
						$v[] = array(
							'page' => $page
						, 'timestamp' => $timestamp
						, 'zipped' => round($size[0] / 1024.0)
						, 'size' => round($size[1] / 1024.0)
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
