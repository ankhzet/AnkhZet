<?php
	require_once 'core_bridge.php';
	require_once 'aggregator.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';

	require_once 'core_parser.php';
	require_once 'core_comparator.php';

	define('UPKIND_SIZE', 0);
	define('UPKIND_GROUP', 1);
	define('UPKIND_DELETE', 2);
	define('UPKIND_INLINE', 3);

	class AuthorWorker {
		public function check($author_id) {
			$g = AuthorsAggregator::getInstance();
			$a = $g->get($author_id);
			preg_match('/^[\/]*(.*?)[\/]*$/', $a['link'], $link);
			$link = $link[1] . '/';
			$t = getutime();
			$data = SAMLIBParser::getData($link, 'author');
			if ($data === false) {
				echo Loc::lget('samlib_down');
				return false;
			} elseif ($data === null) {
				echo Loc::lget('parse_method_unknown');
				return false;
			}

			$s = intval((getutime() - $t) * 1000) / 1000;
			echo "<span style=\"font-size: 80%; color: grey;\">Parser cast for $s sec</span><br />";

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
			$d = $ga->fetch(array('filter' => '`author` = ' . $author_id, 'nocalc' => 1, 'desc' => 0, 'collumns' => '`id`, `group`, `title`, `link`'));
			$gii = array();
			$gl = array();
			$gt = array();
			if ($d['total'])
				foreach ($d['data'] as $row) {
					$g_id = intval($row['id']);
					$gt = array_merge($gt, explode('@', $row['title']));
					$gii[$g_id] = array(0 => $row['group'], 1 => $row['title']);
					$gl[$g_id] = $row['link'];
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


			$new = array_diff($g, $gt);
			if (count($new)) {
				echo Loc::lget('groups_updated') . '<br />';
				foreach ($new as $idx => $title) {
//					debug($groups[$idx], $title);
					$gid = $ga->add(array(
						'author' => $author_id
					, 'group' => $groups[$idx][0]
					, 'link' => $inline[$idx]
					, 'title' => addslashes(htmlspecialchars($title, ENT_QUOTES))
					, 'description' => addslashes(htmlspecialchars($groups[$idx][2], ENT_QUOTES))
					));
					$gi[$gid] = $idx;
					$gl[$gid] = $inline[$idx];
				}
			// ok, groups updated
			}

			$g = array();
			foreach ($gi as $g_id => $idx) {
				$dbhas = isset($gl[$g_id]) && $gl[$g_id];
				$pghas = isset($inline[$idx]) && $inline[$idx];
				if ($dbhas ^ $pghas) // !1 && 2 || 1 && !2
					$g[$g_id] = $inline[$idx];
			}

			$ua = UpdatesAggregator::getInstance();
			foreach ($g as $g_id => $link) {
				$ga->update(array('link' => $link), $g_id);
				$ua->changed($g_id, UPKIND_INLINE, $link && (strpos($link, '/') === false));
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
				, 'collumns' => '`id`, `link`, `size`, `group`'
				));
				if ($d['total']) { // there are smth in db, check it if size diff
					foreach ($d['data'] as $row) {
						$data = $check[$link = $row['link']]; // recently parsed data about page at @link
						unset($check[$link]); // don't check it later in this function

						$page_id = $row['id'];
						if (intval($row['size']) <> $data[2])
							$diff[$page_id] = $link; // check it

						$old_group = intval($row['group']);
						$new_group_i = isset($gi[$old_group]) ? $gi[$old_group] : null;
						if ($new_group_i != $data[0]) {
							echo patternize(Loc::lget('page_changed_group'), $row) . '<br />';
							$new_group = array_search($data[0], $gi);
//							debug(array($new_group_i, $data[0], $new_group, $gi[$new_group]));
							$pa->update(array('group' => $new_group), $page_id);
							$ua->changed($page_id, UPKIND_GROUP, $old_group);
						}
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
						, 'title' => addslashes(htmlspecialchars($data[1], ENT_QUOTES))
						, 'description' => addslashes(htmlspecialchars($data[3], ENT_QUOTES))
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

		function checkGroup($group_id) {
			$ga = GroupsAggregator::getInstance();
			$g = $ga->get($group_id, '`id`, `link`, `author`');
			if ($g['id'] != $group_id) return false;
			$author_id = intval($g['author']);

			$aa = AuthorsAggregator::getInstance();
			$a = $aa->get($author_id, '`link`');
			preg_match('/^[\/]*(.*?)[\/]*$/', $a['link'], $link);
			$link = $link[1] . '/' . $g['link'];
			$data = SAMLIBParser::getData($link, 'group');
			if ($data === false) {
				echo Loc::lget('samlib_down');
				return false;
			} elseif ($data === null) {
				echo Loc::lget('parse_method_unknown');
				return false;
			}

			$links = &$data['links'];
			$check = array();
			// is there anything to check?
			foreach ($links as $link => &$data) //            0      1        2
				if ($data[1] > 0) // size > 0 ?   // link => {title, size, description}
					$check[$link] = $data;

			if (count($check)) { // ok, there are smth to check
				$ua = UpdatesAggregator::getInstance();
				$diff = array();
				$pa = PagesAggregator::getInstance();
				$d = $pa->fetch(array(
					'filter' => '`author` = ' . $author_id . ' and (`link` in ("' . join('", "', array_keys($check)) . '"))'
				, 'nocalc' => 1, 'desc' => 0
				, 'collumns' => '`id`, `link`, `size`, `group`'
				));
				if ($d['total']) { // there are smth in db, check it if size diff
					foreach ($d['data'] as $row) {
						$data = $check[$link = $row['link']]; // recently parsed data about page at @link
						unset($check[$link]); // don't check it later in this function

						$page_id = $row['id'];
						if (intval($row['size']) <> $data[1])
							$diff[$page_id] = $link; // check it

						$old_group = intval($row['group']);
						if ($group_id != $old_group) {
							echo patternize(Loc::lget('page_changed_group'), $row) . '<br />';
							$pa->update(array('group' => $group_id), $page_id);
							$ua->changed($page_id, UPKIND_GROUP, $old_group);
						}
					}
				}

				if (count($diff))
					echo Loc::lget('pages_updated') . '<br />';

				if (count($check)) {
					echo Loc::lget('pages_new') . '<br />';
					foreach ($check as $link => &$data) { // new pages
						$pid = $pa->add(array(
							'author' => $author_id
						, 'group' => $group_id
						, 'link' => $link
						, 'title' => addslashes(htmlspecialchars($data[0], ENT_QUOTES))
						, 'description' => addslashes(htmlspecialchars($data[2], ENT_QUOTES))
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
			$d = $a->get($author, '`link`');
			$alink = $d['link'];
			$q = QueueAggregator::getInstance();

			$worker_stamp = md5(uniqid(rand(),1));
			$update = $q->queue(array_keys($pages), 0);
			$idx = array_keys($pages);
			if (count($update)) {
				$diff = array_intersect($idx, $update);
				echo Loc::lget('already_queued') . ":<br />";
				foreach ($diff as $page) {
					$link = $pages[$page];
					echo "<a href=\"http://samlib.ru/$alink/$link\">$link</a><br />";
				}
				echo '<br />';

				$idx = array_diff($idx, $update);
			}
			echo Loc::lget('queued_pages') . ":<br />";
			foreach ($idx as $page) {
				$link = $pages[$page];
				echo "<a href=\"http://samlib.ru/$alink/$link\">$link</a><br />";
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

					if ($size) {
						if (intval($row['size']) <> ($size = intval($size[2]))) {
							$pa->update(array('size' => $size, 'time' => $time), $page);
							$ua->changed($page, UPKIND_SIZE, $size - intval($row['size']));
							echo " &nbsp;save to [/cache/pages/$page/$time.html]...<br />";
							echo ' &nbsp;updated (' . $size . 'KB).<br />';
						}
						$q->dbc->delete($q->TBL_DELETE, '`page` = ' . $page);
					} else {
						$q->dbc->update($q->TBL_INSERT, array('state' => 0, 'updated' => $time), '`id` = ' . $q_id);
						echo Loc::lget('page_request_failed') . '<br />';
					}
					$done++;
					if (($timeout > 0) && (time() - $t > $timeout))
						return $left - $done;
				}
				return 0;
			} else
				echo Loc::lget('nothing_to_update') . '<br />';

			return 0;
		}

		function groupsToUpdate($force = 0) {
			$dbc = msqlDB::o();
			$t = time() - ($force ? 5 : 60 * 30); // 30 minutes
			$s = $dbc->select('groups'
			, '`time` < ' . $t . ' and `link` <> "" and `link` not like "/%" order by `time`'
			, '`id` as `0`'
			);
			$a = array();
			if ($s)
				foreach($dbc->fetchrows($s) as $row)
					$a[] = intval($row[0]);

			return $a;
		}

	}

	class UpdatesAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'updates';
		var $TBL_INSERT = 'updates';
		var $TBL_DELETE = 'updates';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`page` int not null'
		, '`kind` tinyint null default 0'
		, '`value` int null default 0'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 10;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

		function getUpdates($limit) {
			$d = $this->fetch(array(
				'desc' => true
			, 'page' => $limit - 1
			, 'pagesize' => $this->FETCH_PAGE
			));
			$r = array();
			if ($d['total']) {
				$a = array();
				$g = array();
				$p = array();
				foreach ($d['data'] as &$row) {
					switch ($row['kind']) {
					case UPKIND_INLINE: // group updates
						$g[intval($row['page'])] = 1;
						break;
					case UPKIND_GROUP:
						$g[intval($row['value'])] = 1;
					default:
						$p[intval($row['page'])] = 1;
						break;
					}
				}
				$aa = AuthorsAggregator::getInstance();
				$pa = PagesAggregator::getInstance();
				$ga = GroupsAggregator::getInstance();
				$pd = $pa->get(array_keys($p), '`id`, `title`, `author`, `group`');
				foreach ($pd as &$row) {
					$p[intval($row['id'])] = $row;
					$g[intval($row['group'])] = 1;
					$a[intval($row['author'])] = 1;
				}
				$gd = $ga->get(array_keys($g), '`id`, `title`, `author`');
				$g = array();
				foreach ($gd as &$row) {
					$g[intval($row['id'])] = $row;
					$a[intval($row['author'])] = 1;
				}
				$ad = $aa->get(array_keys($a), '`id`, `fio`');
				$a = array();
				foreach ($ad as &$row)
					$a[intval($row['id'])] = $row['fio'];

				foreach ($d['data'] as &$row) {
					switch ($kind = $row['kind']) {
					case UPKIND_INLINE: // group updates
						$gid = intval($row['page']);
						$aid = $g[$gid]['author'];
						$r[] = array('id' => $gid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'group' => $gid
						, 'group_title' => $g[$gid]['title']
						, 'author' => $aid
						, 'fio' => $a[$aid]
						);
						break;
					case UPKIND_GROUP:
						$pid = intval($row['page']);
						$gid = $p[$pid]['group'];
						$aid = $p[$pid]['author'];
						$goid = intval($row['value']);
						$r[] = array('id' => $gid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'page' => $pid
						, 'title' => $p[$pid]['title']
						, 'group' => $gid
						, 'group_title' => $g[$gid]['title']
						, 'old_id' => $goid
						, 'old_title' => ($t = uri_frag($g, $goid, 0, 0)) ? $t['title'] : '&lt;!&gt;'
						, 'author' => $aid
						, 'fio' => $a[$aid]
						);
						break;
					default: // page updates
						$pid = intval($row['page']);
						$aid = $p[$pid]['author'];
						$gid = $p[$pid]['group'];
						$r[] = array('id' => $pid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'page' => $pid
						, 'title' => $p[$pid]['title']
						, 'group' => $gid
						, 'group_title' => $g[$gid]['title']
						, 'author' => $aid
						, 'fio' => $a[$aid]
						);
						break;
					}
				}
			}
			return array('data' => $r, 'total' => $d['total']);
		}

		function changed($page, $kind, $value) {
			return $this->add(array('page' => $page, 'kind' => $kind, 'value' => $value));
		}

	}

?>