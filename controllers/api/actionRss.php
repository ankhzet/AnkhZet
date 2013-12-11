<?php
	require_once 'core_rss.php';
	require_once 'core_page.php';
	require_once 'core_authors.php';

	class actionRss {
		function execute($params) {
			$rss = RSSWorker::get();
			$time = time();
			$c = Config::read('INI', 'cms://config/config.ini');
			$data = array();
			$data['title'] = $c->get('site-title');
			$data['link'] = 'http://' . $_SERVER['HTTP_HOST'] . '/';
			$data['description'] = $data['title'];
			$data['generator'] = $c->get('rss-feeder');
			$data['lastbuilddate'] = date('r', $time);
			$data['self'] = "{$data['link']}rss.xml";

			msqlDB::o()->debug = post('debug');

			$uid = uri_frag($params, 'channel');
			$author = uri_frag($params, 'author');
			$page = uri_frag($params, 'page');
			$limit = uri_frag($params, 'last');

			$limit = $limit ? $limit : 30;

			switch (true) {
			case !!$uid:
				$u = User::get($uid);
				if (!$u->valid())
					$this->_404('Unknown channel UID');

				$t = array_merge($data, array('sub-channel' => htmlspecialchars($u->readable())));
				$data['title'] = patternize(Loc::lget('rss-title-channel'), $t);
				$data['self'] = "{$data['link']}rss.xml?channel=$uid";

				$pageIDs = $this->fetchForUID($uid, $pages, $limit);
				break;
			case !!$author:
				$aa = AuthorsAggregator::getInstance();
				$a = $aa->get($author, 'id as `0`, fio as `1`');
				if (intval($a[0]) != $author)
					$this->_404('Unknown author UID');

				$t = array_merge($data, array('sub-channel' => htmlspecialchars($a[1])));
				$data['title'] = patternize(Loc::lget('rss-title-author'), $t);
				$data['self'] = "{$data['link']}rss.xml?author=$author";

				$pageIDs = $this->fetchForAuthor($author, $pages, $limit);
				break;
			case !!$page:
				$pa = PagesAggregator::getInstance();
				$p = $pa->get($page, 'id as `0`, title as `1`');
				if (intval($p[0]) != $page)
					$this->_404('Unknown page UID');

				$t = array_merge($data, array('sub-channel' => htmlspecialchars($p[1])));
				$data['title'] = patternize(Loc::lget('rss-title-page'), $t);
				$data['self'] = "{$data['link']}rss.xml?page=$page";
				$pageIDs = $this->fetchForPage($page, $pages, $limit);
				break;
			default:
//				$pageIDs = $h->fetchUpdates($uid, $pages);
			}

			$i = array();
			if ($pageIDs) {
				$this->fetchData($pageIDs, $pageData, $authorData, $groupData);

				foreach ($pages as $hID => $row) {
					$page_id = intval($row['page']);
					$author = intval($pageData[$page_id]['author']);
					$group = intval($pageData[$page_id]['group']);
					$alink = $authorData[$author]['link'];
					$link = $pageData[$page_id]['link'];
					$slink = str_replace('.shtml', '', $link);
					$delta = intval($row['size']) - intval($row['size_old']);
					$old_version_date = date('d-m-Y/H-i-s', $row['time_old']);
					$i[$hID] = array(
						'title' => $row['title']
					, 'author' => $author ? $authorData[$author]['fio'] : '&lt;неизвестный автор&gt;'
					, 'group' => $group ? $groupData[$group] : '&lt;неизвестная группа&gt;'
					, 'link' => "{$data['link']}pages/version/{$page_id}/$old_version_date"
					, 'samlib' => "$alink/$slink"
					, 'pubDate' => date('r', $row['time'])
					, 'guid' => md5($page_id . $row['time_old'] . $delta)
					);

					$diff = ($delta < 0) ? 'red' : 'green';
					$i[$hID]['description'] = array(
						'title' => $i[$hID]['title']
					, 'group' => $i[$hID]['group']
					, 'author' => $i[$hID]['author']
					, 'size' => $row['size']
					, 'delta' => (($delta < 0) ? '' : '+') . $delta
					, 'diff' => $diff
					, 'samlib' => "$alink/$link"
					, 'link' => $i[$hID]['link']
					, 'link2' => "{$data['link']}pages/id/{$page_id}"
					, 'pubdate' => date('d.m.Y H:i:s', $row['time'])
					, 'description' => safeSubstr($row['description'], 200, 3)
					);
				}
			}

			$data['items'] = $i;

			$gzip = !post_int('nogzip');
			$debug = post_int('debug');
			if ($gzip) {
//				ob_start("ob_gzhandler");
//				$rss = gzcompress($rss);
				header('Content-Encoding: gzip');
			}

			switch (post('type')) {
			case 'json':
				require_once 'json.php';
				$rss = JSON_Result(JSON_Ok, $data, false);
				$content_type = "json";
				$file = "rss.json";
				break;
			default:
				$rss = $rss->format($data);
				$content_type = "rss+xml";
				$file = "rss.xml";
			}

			if (!$debug)
			header("Content-Type: application/$content_type; charset=UTF-8");
			if (!$gzip)
			header('Content-Length: ' . strlen($rss));
			if (!$debug)
			header("Content-Disposition: inline; filename=$file");
//			$rss = ob_get_contents();

			header('Cache-Control: no-store; no-cache');

			if ($gzip) $rss = gzencode($rss);

			echo $rss;

//			if ($gzip)
//				ob_end_flush();

			die();
			return true;
		}
		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}

		function fetchData($pageIds, &$pageData, &$authorData, &$groupData) {
			$pa = PagesAggregator::getInstance();
			$pd = $pa->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => '`id` in (' . join(',', $pageIds) . ')'
			, 'collumns' => '`id`, `link`, `author`, `group`'));

			$pageData = array();
			foreach ($pd['data'] as &$row)
				$pageData[intval($row['id'])] = $row;

			$aa = AuthorsAggregator::getInstance();
			$ad = $aa->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => '`id` in (' . join(',', array_unique(fetch_field($pageData, 'author'))) . ')'
			, 'collumns' => '`id`, `fio`, `link`'));

			$authorData = array();
			foreach ($ad['data'] as &$row)
				$authorData[intval($row['id'])] = $row;

			$ga = GroupsAggregator::getInstance();
			$gd = $ga->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => '`id` in (' . join(',', array_unique(fetch_field($pageData, 'group'))) . ')'
			, 'collumns' => '`id`, `title`'));

			$groupData = array();
			foreach ($gd['data'] as &$row)
				$groupData[intval($row['id'])] = $row['title'];
		}

		function fetchForUID($uid, &$pages, $limit) {
			require_once 'core_history.php';
			$f = HistoryAggregator::getInstance()->fetchUpdates($uid);

			$pages = array();
			if (!!$f) {
				$f = array_slice($f, 0, $limit);

				foreach ($f as &$row)
					$pages[intval($row['page'])] = $row;

				return array_keys($pages);
			} else
				return null;
		}

		function fetchForAuthor($author, &$pages, $limit) {
			require_once 'core_updates.php';
			$dbc = msqlDB::o();
			$f1 = array(UPKIND_SIZE, UPKIND_DELETE, UPKIND_ADDED, UPKIND_DELETED);
			$f1 = join(',', $f1);
			$s = $dbc->select('pages p, updates u'
			, "p.author = $author and u.kind in ($f1) and u.page = p.id order by u.time desc limit $limit"
			, 'u.id as `0`, p.id as `page`, p.`description`, p.`size`, u.`time`, p.`title`, u.`value` as `delta`, u.`time` as `time_old`'
			);
			$f = $dbc->fetchrows($s);
			$pages = array();
			$pageIDs = array();
			$sizes = array();
			foreach ($f as &$row) {
				$pageIDs[] = $pid = intval($row['page']);
				$sizes[$pid] = (isset($sizes[$pid]) ? $sizes[$pid] : intval($row['size'])) - intval($row['delta']);
				$row['size'] = $sizes[$pid] + intval($row['delta']);
				$row['size_old'] = $sizes[$pid];
				$pages[] = $row;
			}

			return array_unique($pageIDs);
		}
		function fetchForPage($page, &$pages, $limit) {
			require_once 'core_updates.php';
			$dbc = msqlDB::o();
			$f1 = array(UPKIND_SIZE, UPKIND_DELETE, UPKIND_ADDED, UPKIND_DELETED);
			$f1 = join(',', $f1);
			$s = $dbc->select('pages p, updates u'
			, "p.id = $page and u.kind in ($f1) and u.page = p.id order by u.time desc limit $limit"
			, 'u.id as `0`, p.id as `page`, p.`description`, p.`size`, u.`time`, p.`title`, u.`value` as `delta`, u.`time` as `time_old`'
			);
			$f = $dbc->fetchrows($s);
			$pages = array();
			$pageIDs = array();
			$sizes = array();
			foreach ($f as &$row) {
				$pageIDs[] = $pid = intval($row['page']);
				$sizes[$pid] = (isset($sizes[$pid]) ? $sizes[$pid] : intval($row['size'])) - intval($row['delta']);
				$row['size'] = $sizes[$pid] + intval($row['delta']);
				$row['size_old'] = $sizes[$pid];
				$pages[] = $row;
			}

			return array_unique($pageIDs);
		}
	}

	function fetch_field($arr, $field) {
		$f = array();
		foreach ($arr as $row)
			$f[] = $row[$field];

		return $f;
	}