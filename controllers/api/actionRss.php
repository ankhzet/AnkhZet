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
			$uid = $u->ID();
			if (!$uid)
				$this->_404('Unknown channel UID');

			$time = time();
			$c = Config::read('INI', '_engine/config.ini');
			$data = array();
			$data['title'] = $c->get('site-title');
			$data['link'] = 'http://' . $_SERVER['HTTP_HOST'] . '/';
			$data['description'] = $data['title'];
			$data['generator'] = $c->get('rss-feeder');
			$data['lastbuilddate'] = date('r', $time);


			$h = HistoryAggregator::getInstance();
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
				, 'filter' => '`id` in (' . join(',', array_keys($pages)) . ')'
				, 'collumns' => '`id`, `link`, `author`, `group`, `time`'));

				$pa = array();
				foreach ($pd['data'] as &$row)
					$pa[intval($row['id'])] = $row;


				$a = AuthorsAggregator::getInstance();
				$ad = $a->fetch(array('nocalc' => 1, 'desc' => 0
				, 'filter' => '`id` in (' . join(',', fetch_field($pa, 'author')) . ')'
				, 'collumns' => '`id`, `fio`, `link`'));

				$g = GroupsAggregator::getInstance();
				$gd = $g->fetch(array('nocalc' => 1, 'desc' => 0
				, 'filter' => '`id` in (' . join(',', fetch_field($pa, 'group')) . ')'
				, 'collumns' => '`id`, `title`'));

				$at = array();
				foreach ($ad['data'] as &$row)
					$at[intval($row['id'])] = $row;

				$gt = array();
				foreach ($gd['data'] as &$row)
					$gt[intval($row['id'])] = $row['title'];

				foreach ($pages as $page_id => $row) {
					$i[$page_id] = array(
						'title' => $row['title']
					, 'author' => $at[$pa[$page_id]['author']]['fio']
					, 'group' => $gt[$pa[$page_id]['group']]
					, 'link' => $data['link'] . 'pages/id/' . $page_id
					, 'pubdate' => date('r', $row['lastseen'])
					, 'guid' => md5($row[0] . $time)
					);

					$delta = intval($row['size']) - intval($row['size_old']);
					$diff = ($delta < 0) ? 'red' : 'green';
					$i[$page_id]['description'] = array(
						'title' => $i[$page_id]['title']
					, 'group' => $i[$page_id]['group']
					, 'author' => $i[$page_id]['author']
					, 'size' => $row['size']
					, 'delta' => (($delta < 0) ? '' : '+') . $delta
					, 'diff' => $diff
					, 'samlib' => $at[$pa[$page_id]['author']]['link'] . '/' . $pa[$page_id]['link']
					, 'link' => $i[$page_id]['link']
					, 'pubdate' => date('d.m.Y h:i:s', $pa[$page_id]['time'])
					, 'description' => safeSubstr($row['description'], 200, 3)
					);
				}

				$h->upToDate(fetch_field($pages, '0'));
			}

			$data['items'] = $i;
			$rss->output($data, true);
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