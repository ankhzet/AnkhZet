<?php
	require_once 'core_bridge.php';
	require_once 'aggregator.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';

	require_once 'core_comparator.php';

	class AuthorWorker {
		public function check($author_id) {
			$g = AuthorsAggregator::getInstance();
			$a = $g->get($author_id);
			preg_match('/^[\/]*(.*?)[\/]*$/', $a['link'], $link);
			$link = $link[1] . '/';
			$url  = 'http://samlib.ru/' . $link;
//			$hash = base64_encode($link);
//			$store = SUB_DOMEN . '/cache/' . $hash;

//			if (!is_file($store)) {
				$html = url_get_contents($url);
//				if ($html !== false)
//					file_put_contents($store, gzcompress/**/($html));
//			} else
//				$html = gzuncompress/**/(file_get_contents($store));

			$html = mb_convert_encoding($html, 'UTF-8', 'CP1251');
			if (($html === false) || trim($html) == '') {
				echo Loc::lget('samlib_down');
				return false;
			}

			$data = $this->parseAuthor($html);
			$groups = $data[0];
			$inline = $data[1];
			$links = $data[2];
			$fio = $data[3];
			if ($fio != $a['fio']) {
				echo Loc::lget('author_updated') . '<br />';
			}
			$g->update(array('fio' => $fio, 'time' => time()), $author_id);

//			debug2($html);
//			debug2($groups);
//			debug2($inline);
//			debug2($links);
//			die();

			// check updates
			$ga = GroupsAggregator::getInstance();
			$d = $ga->fetch(array('filter' => '`author` = ' . $author_id, 'nocalc' => 1, 'desc' => 0, 'collumns' => '`id`, `group`, `title`'));
			$gii = array();
			$gt = array();
			if ($d['total'])
				foreach ($d['data'] as $row) {
					$gt = array_merge($gt, explode('@', $row['title']));
					$gii[intval($row['id'])] = array(0 => $row['group'], 1 => $row['title']);
				}
//			debug($gt);

			$g = array();
			$gi = array();
//				debug($gii);
			foreach ($groups as $idx => &$_) {
//				debug($_);
				$g[$idx] = $_[1];
				foreach ($gii as $gid => $d)
					if (($_[1] == $d[1]) && ($d[0] == $_[0]))
						$gi[$gid] = $idx;
			}


//				debug($g);

			$new = array_diff($g, $gt);
//			debug($new);
			if (count($new)) {
				echo Loc::lget('groups_updated') . '<br />';
				foreach ($new as $idx => $title) {
//					debug($groups[$idx], $title);
					$gid = $ga->add(array(
						'author' => $author_id
					, 'group' => $groups[$idx][0]
					, 'link' => $inline[$idx]
					, 'title' => $title
					, 'description' => $groups[$idx][2]
					));
					$gi[$gid] = $idx;
				}
			// ok, groups updated
			}

//			debug($gi, 'gi');

			$check = array();
			// is there anything to check?
			foreach ($links as $idx => &$block)
				foreach ($block as $link => &$data) //            0      1     2         3
					if ($data[2] > 0) // size > 0 ?   // link => {group, title, size, description}
						$check[$link] = $data;

			if (count($check)) { // ok, there are smth to check
				$diff = array();
				$pa = PagesAggregator::getInstance();
				$d = $pa->fetch(array(
					'filter' => '`author` = ' . $author_id . ' and (`link` in ("' . join('", "', array_keys($check)) . '"))'
				, 'nocalc' => 1, 'desc' => 0
				, 'collumns' => '`id`, `link`, `size`'
				));
				if ($d['total']) { // there are smth in db, check it if size diff
					foreach ($d['data'] as $row) {
						$data = $check[$link = $row['link']]; // recently parsed data about page at @link
						unset($check[$link]); // don't check it later in this function

						if (intval($row['size']) <> $data[2])
							$diff[$row['id']] = $link; // check it
					}
				}
				if (count($diff))
					echo Loc::lget('pages_updated') . '<br />';

				if (count($check)) {
					echo Loc::lget('pages_new') . '<br />';
					foreach ($check as $link => &$data) { // new pages
						$idx = $data[0];
						$gid = array_search($idx, $gi);
						$pid = $pa->add(array(
							'author' => $author_id
						, 'group' => $gid
						, 'link' => $link
						, 'title' => htmlspecialchars($data[1])
						, 'description' => htmlspecialchars($data[3])
						, 'size' => 0 // later it will be updated with diff checker
						));
						$diff[$pid] = $link;
					}
				}

				if (count($diff)) {
					echo Loc::lget('pages_queue') . '<br />';
					$this->queuePages($author_id, $diff);
				} else
					echo Loc::lget('no_updates') . '<br />';
			} else
				echo Loc::lget('no_updates') . '<br />';
		}

		function queuePages($author, $pages) {
			$a = AuthorsAggregator::getInstance();
			$d = $a->get($author);
			$q = QueueAggregator::getInstance();

			$worker_stamp = md5(uniqid(rand(),1));
			$update = $q->queue(array_keys($pages), 0);
			$idx = array_keys($pages);
			if (count($update)) {
				$diff = array_intersect($idx, $update);
				echo Loc::lget('already_queued') . ":<br />";
				foreach ($diff as $page) {
					$link = $pages[$page];
					echo "<a href=\"http://samlib.ru/$d[link]/$link\">$link</a><br />";
				}
				echo '<br />';

				$idx = array_diff($idx, $update);
			}
			echo Loc::lget('queued_pages') . ":<br />";
			foreach ($idx as $page) {
				$link = $pages[$page];
				echo "<a href=\"http://samlib.ru/$d[link]/$link\">$link</a><br />";
			}
		}

		function serveQueue($limit = 1, $timeout = 0) {
			$t = time();

			$q = QueueAggregator::getInstance();
			$d = $q->fetch(array('desc' => 0
			, 'filter' => '(`state` = 0) or (`state` <> 0 and `updated` < ' . ($t - QUEUE_FAILTIME) . ') limit ' . $limit
			, 'collumns' => '`id` as `0`, `page` as `1`'
			));

			if ($d['total']) {
				echo '> ' . $d['total'] . ' pages waits for update...<br />';
				foreach ($d['data'] as $row)
					$u[intval($row[1])] = intval($row[0]);

				$s = $q->dbc->select('`pages` p, `authors` a'
				, 'p.`id` in (' . join(',', array_keys($u)) . ') and p.`author` = a.`id`'
				, 'p.`id`, p.`link`, p.`size`, a.`link` as `author`, p.`time`');
				$pages = $q->dbc->fetchrows($s);
				$left = count($pages);
				$done = 0;

				$c = new PageComparator();
				$pa = PagesAggregator::getInstance();
				$ua = UpdatesAggregator::getInstance();
				$worker_stamp = md5(uniqid(rand(), 1));
				foreach ($pages as $row) {
					$page = intval($row['id']); // id of Page
					$q_id = $u[$page]; // id of Queue row
					$time = time();
					$s = $q->dbc->update(
						$q->TBL_INSERT
					, array('state' => $worker_stamp, 'updated' => $time)
					, '`id` = ' . $q_id
					);

					echo "&gt; U@{$q_id}, ID#{$page}: {$row['link']}...<br />";
					$size = $c->compare($page, "{$row['author']}/{$row['link']}", $time);

					/* reconnect mysql DB (preventing "MySQL server has gone away") */
					$pa->dbc->close();
					$pa->dbc->connect();
					$pa->dbc->open();
					/* - */

					if (intval($row['size']) <> ($size = intval($size[2]))) {
						$pa->update(array('size' => $size, 'time' => $time), $page);
						$ua->queue($page, $size - intval($row['size']));
						echo " &nbsp;save to [/cache/pages/$page/$time.html]...<br />";
						echo ' &nbsp;updated (' . $size . 'KB).<br />';
					}
					$q->dbc->delete($q->TBL_DELETE, '`page` = ' . $page);
					$done++;
					if (($timeout > 0) && (time() - $t > $timeout))
						return $left - $done;
				}
				return 0;
			}
			return 0;
		}

		function parseAuthor($html) {
			$p1 = '- Блок ссылок на произведения -';
			$p2 = '- Подножие -';
			$i1 = strpos($html, $p1);
			$i2 = strpos($html, $p2, $i1);
			$m = substr($html, $i1, $i2 - $i1);
			$m = preg_replace('/(<!-+|--+>)/', '', $m);

			preg_match_all('/<a name="?gr(\d+)"?>(.*?)(<gr|<\/a)/i', $m, $g, PREG_SET_ORDER);
			$groups = array();
			$inline = array();
			foreach($g as $match) {
				$id = intval($match[1]);

				$t = preg_replace('/((<[^>]+>)|([<\/]))/', '', $match[2]);
				$t = ucfirst(preg_replace('/:$/', '', $t));
				$groups[] = array($id, $t);

				if (preg_match('/a href="?([^ ">]+)"?/i', $match[2], $t))
					$inline[count($groups) - 1] = $t[1];
			}

//			debug($groups);
//			debug($inline);

			$pieces = explode('<a name=gr', $m);
			unset($pieces[0]);

			$links = array();
			foreach ($groups as $idx => &$g) {
				$id = $g[0];
				$block = array_shift($pieces);
				preg_match('/<br>(.*?)<(dl|\/small)>/ism', $block, $t1);
				$g[2] = trim(preg_replace(array('/<br( \/)?>/i', '/<[^>]+>/'), array(PHP_EOL, ''), $t1[1]));
//				debug($g[2]);
				preg_match_all('/<dl>(.*?)<\/dl>/i', $block, $t);
//				debug($t, htmlspecialchars($block));
				foreach ($t[1] as $link) {
					preg_match('/<a href="?([^ >"]+)"?>(.*?)<\/a>[^<]*<b>(.*?)k<\/b>/i', $link, $t2);
					preg_match('/<dd>(.*)/i', $link, $t3);
//					debug($t2, htmlspecialchars($link));
					$links[$id][$t2[1]] = array($idx, strip_tags($t2[2]), intval($t2[3]), strip_tags($t3[1], '<br><p>'));
				}
//				debug($links[$id], "Group #$id");
			}
//			debug($m);
//			debug($html);
			preg_match('/<h3>(.*?)<br/i', $html, $t);
			$t = preg_replace('/:$/', '', $t[1]);
			return array($groups, $inline, $links, $t);
		}
	}

	class UpdatesAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = '`updates` u, `pages` p, `authors` a, `groups` g';
		var $TBL_INSERT = 'updates';
		var $TBL_DELETE = 'updates';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`page` int not null'
		, '`size` int null default 0'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 10;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

		function getUpdates($page) {
			$d = $this->fetch(array(
				'filter' => 'u.`page` = p.`id` and a.`id` = p.`author` and g.`id` = p.`group`'
			, 'collumns' => 'p.`id`, p.`title`, u.`size`, a.`fio`, p.`author`, g.`title` as `group_title`, p.`group`, u.`time`'
			, 'desc' => true
			, 'page' => $page
			));
			return $d;
		}

		function queue($page, $size) {
			return $this->add(array('page' => $page, 'size' => $size));
		}
	}

?>