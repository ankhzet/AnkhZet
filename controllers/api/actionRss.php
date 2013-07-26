<?php
	require_once 'core_rss.php';
	require_once 'core_history.php';

	class actionRss {
		function execute($params) {
			$rss = RSSWorker::get();
			$uid = intval($params['channel']);
			if (!$uid)
				$this->_404('Channel UID not specified');

			$u = User::get($uid);
			if (!$u->valid())
				$this->_404('Unknown channel UID');

			$time = time();
			$c = Config::read('INI', 'cms://config/config.ini');
			$data = array();
			$data['title'] = $c->get('site-title');
			$data['link'] = 'http://' . $_SERVER['HTTP_HOST'] . '/';
			$data['description'] = $data['title'];
			$data['generator'] = $c->get('rss-feeder');
			$data['lastbuilddate'] = date('r', $time);


			$h = HistoryAggregator::getInstance();
			$h->dbc->debug = post('debug');
			$f = $h->fetchUpdates($uid);
			$i = array();
			if (count($f)) {
				$pages = array();
				foreach ($f as &$row)
					$pages[intval($row['page'])] = $row;

				$h_ids = array_keys($pages);

				require_once 'core_page.php';
				require_once 'core_authors.php';

				$p = PagesAggregator::getInstance();
				$pd = $p->fetch(array('nocalc' => 1, 'desc' => 0
				, 'filter' => '`id` in (' . join(',', $h_ids) . ')'
				, 'collumns' => '`id`, `link`, `author`, `group`'));

				$pa = array();
				foreach ($pd['data'] as &$row)
					$pa[intval($row['id'])] = $row;


				$a = AuthorsAggregator::getInstance();
				$ad = $a->fetch(array('nocalc' => 1, 'desc' => 0
				, 'filter' => '`id` in (' . join(',', array_unique(fetch_field($pa, 'author'))) . ')'
				, 'collumns' => '`id`, `fio`, `link`'));

				$g = GroupsAggregator::getInstance();
				$gd = $g->fetch(array('nocalc' => 1, 'desc' => 0
				, 'filter' => '`id` in (' . join(',', array_unique(fetch_field($pa, 'group'))) . ')'
				, 'collumns' => '`id`, `title`'));

				$at = array();
				foreach ($ad['data'] as &$row)
					$at[intval($row['id'])] = $row;

				$gt = array();
				foreach ($gd['data'] as &$row)
					$gt[intval($row['id'])] = $row['title'];

				foreach ($pages as $page_id => $row) {
					$author = intval($pa[$page_id]['author']);
					$group = intval($pa[$page_id]['group']);
					$alink = $at[$author]['link'];
					$link = $pa[$page_id]['link'];
					$slink = str_replace('.shtml', '', $link);
					$delta = intval($row['size']) - intval($row['size_old']);
					$i[$page_id] = array(
						'title' => $row['title']
					, 'author' => $author ? $at[$author]['fio'] : '&lt;неизвестный автор&gt;'
					, 'group' => $group ? $gt[$group] : '&lt;неизвестная группа&gt;'
					, 'link' => "{$data['link']}pages/version/{$page_id}?version={$row['time_old']}"
					, 'samlib' => "$alink/$slink"
					, 'pubDate' => date('r', $row['time'])
					, 'guid' => md5($page_id . $row['time_old'] . $delta)
					);

					$diff = ($delta < 0) ? 'red' : 'green';
					$i[$page_id]['description'] = array(
						'title' => $i[$page_id]['title']
					, 'group' => $i[$page_id]['group']
					, 'author' => $i[$page_id]['author']
					, 'size' => $row['size']
					, 'delta' => (($delta < 0) ? '' : '+') . $delta
					, 'diff' => $diff
					, 'samlib' => "$alink/$link"
					, 'link' => $i[$page_id]['link']
					, 'link2' => "{$data['link']}pages/id/{$page_id}"
					, 'pubdate' => date('d.m.Y H:i:s', $row['time'])
					, 'description' => safeSubstr($row['description'], 200, 3)
					);
				}

//				$h->upToDate(fetch_field($pages, '0'));
			}

			$data['items'] = $i;
			$gzip = !post_int('nogzip');
			$debug = post_int('debug');
			if ($gzip) {
				ob_start("ob_gzhandler");
//				$rss = gzcompress($rss);
//				header('Content-Encoding: gzip');
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

			echo $rss;

			if ($gzip)
				ob_end_flush();

			die();
			return true;
		}
		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}
	}

	function fetch_field($arr, $field) {
		$f = array();
		foreach ($arr as $row)
			$f[] = $row[$field];

		return $f;
	}