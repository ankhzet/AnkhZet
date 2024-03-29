<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'core_bridge.php';
	require_once 'aggregator.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';
	require_once 'core_pagecontroller_utils.php';

	require_once 'core_parser.php';
	require_once 'core_comparator.php';

	define('UPKIND_SIZE', 0); // page-related
	define('UPKIND_GROUP', 1); // page-related
	define('UPKIND_DELETE', 2); // page-related ???
	define('UPKIND_INLINE', 3); // group-related
	define('UPKIND_DELETED_GROUP', 4); // group-related
	define('UPKIND_ADDED', 5); // page-related
	define('UPKIND_DELETED', 6); // page-related
	define('UPKIND_RENAMED', 7); // page-related

	function echo_log($text, $postfix = '') {
		echo Loc::lget($text) . $postfix . '<br />' . PHP_EOL;
		return false;
	}

	function normalizeQuotedStr($str) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl2 = array_flip ($trans_tbl);

		$s1 = str_replace('&#039;', '\'', $str);
		$s2 = strtr($s1, $trans_tbl2);
		$s3 = htmlspecialchars($s2);
		$s4 = str_replace('\'', '&#039;', $s3);

		return $s4;
	}

	class AuthorWorker {
		public function check($author_id) {
			$result = array();
			$authorsAggregator = AuthorsAggregator::getInstance();
			$a = $authorsAggregator->get($author_id);
			preg_match('/^[\/]*(.*?)[\/]*$/', $a['link'], $link);
			$link = $link[1] . '/';
			$t = getutime();
			$data = SAMLIBParser::getData($link, 'author');
			if ($data === false)
				return array('error' => Loc::lget('samlib_down'));
///				return echo_log('samlib_down');
			elseif ($data === null)
				return array('error' => Loc::lget('parse_method_unknown'));
///				return echo_log('parse_method_unknown');;

			$result['load-timings'] = $data['timings'];

			$s = intval((getutime() - $t) * 1000) / 1000;
			$result['cast-for'] = $s;
///			echo "<span style=\"font-size: 80%; color: grey;\">Parser cast for $s sec</span><br />";

			$groups = $data[0];
			$inline = $data[1];
			$links = $data[2];
			$fio = $data[3];
			if ($fio != $a['fio'])
				$result['author-updated'] = true;
///				echo_log('author_updated');

			$authorsAggregator->update(array('fio' => $fio, 'time' => time()), $author_id, true);

//			debug2($html);
//			debug2($groups);
//			debug2($inline);
//			debug2($links);
//			die();

			// check updates
			$ga = GroupsAggregator::getInstance();
			$d = $ga->fetch(array('filter' => "`author` = $author_id", 'nocalc' => 1, 'desc' => 0, 'collumns' => '`id`, `group`, `title`, `link`'));
			$gii = array();
			$gl = array();
			$gt = array();
			if ($d['total'])
				foreach ($d['data'] as &$row) {
					$g_id = intval($row['id']);
					$gt = array_merge($gt, explode('|@|', $row['title']));
					$gii[$g_id] = array(0 => $row['group'], 1 => $row['title'], 2 => $row);
					$gl[$g_id] = $row['link'];
				}
//			debug($gt);

			$g = array();
			$gi = array();
			$g_changes = array();
//				debug($gii);
			// for each groups
			foreach ($groups as $idx => &$_) {
//				debug($_);
				$g[$idx] = ((isset($inline[$idx]) && $inline[$idx]) ? '@' : '') . $_[1];
				// for each group in db
				foreach ($gii as $gid => $d)
					// if titles is the same (previously group num also was compared)
					if (($g[$idx] == $d[1])) {
						// save new group index
						$gi[$gid] = $idx;
						if ($d[0] != $_[0]) // group number changed!
							$g_changes[$gid] = array('group' => $_[0]); // new group number

						break;
					}
			}


//			debug($g);
//			debug($gi);
			// $g - groups parsed, $gt - groups titles in db
			$new = array_diff($g, $gt); // added groups (new - old)
			$old = array_diff($gt, $g); // deleted groups (old - new)

//			debug (array($old, $new), 'deleted + added');

			if (count($new)) { // if groups on page differs from db
				$result['groups-updated'] = true;
///				echo_log('groups_updated');
				foreach ($new as $idx => $title) { // add new group
//					debug($groups[$idx], $title);
					$link = isset($inline[$idx]) ? $inline[$idx] : '';
					$gid = $ga->add(array(
						'author' => $author_id
					, 'group' => $groups[$idx][0]
					, 'link' => $link
					, 'title' => addslashes(htmlspecialchars($title, ENT_QUOTES))
					, 'description' => addslashes(htmlspecialchars($groups[$idx][2], ENT_QUOTES))
					));
					$gi[$gid] = $idx; // save local index on page for group
					$gl[$gid] = $link; // save group link
				}
			// ok, groups updated
			}

			$g = array();
			foreach ($gi as $g_id => $idx) {
				$dbhas = isset($gl[$g_id]) && $gl[$g_id]; // group in db has link
				$pghas = isset($inline[$idx]) && $inline[$idx]; // group on page has link
				if ($dbhas ^ $pghas) // !1 && 2 || 1 && !2 - link changed
					$g[$g_id] = $inline[$idx];
			}

			$ua = UpdatesAggregator::getInstance();
			foreach ($g as $g_id => $link) {
				$change = array('link' => $link);
				$g_changes[$g_id] = isset($g_changes[$g_id]) ? array_merge($g_changes[$g_id], $change) : $change;
				$ua->changed($g_id, UPKIND_INLINE, $link && (strpos($link, '/') === false));
			}

			foreach ($g_changes as $g_id => $values)
				$ga->update($values, $g_id);

			if (!!$old) { // there are deleted groups
				$result['groups-deleted'] = true;
///				echo_log('groups_to_delete');
				$diff = array_diff(array_keys($gii), array_keys($gi));
				$group_filter = join(',', $diff);
//				debug(array($old, $diff), 'groups to delete');
				$pa = PagesAggregator::getInstance();
				$d = $pa->fetch(array(
					'filter' => "`author` = $author_id and (`group` in ($group_filter))"
				, 'nocalc' => 1, 'desc' => 0
				, 'collumns' => '`id`, `group`, `title`, `link`'
				));
				if ($d['total']) {
					$t = array();
					$candidates_to_move = array();

					foreach ($d['data'] as &$row)
						$t[intval($row['group'])][] = array(intval($row['id']), $row['link']);

					foreach ($t as $group => $pages_data) {
						foreach ($pages_data as $page_data) {
							$found = false;
							$data_link = null;
							foreach ($links as $ids => &$block) {
								foreach ($block as $link => &$data)
									if ($link == $page_data[1]) {
										$found = $data[0];
										$data_link = &$data;
										break;
									}
								if ($found !== false) break;
							}

							$new_group_id = ($found !== false) ? array_search($found, $gi) : false;
//							debug(array($found, $new_group_id));
							if ($new_group_id !== false) {
								$page_data[2] = $new_group_id;
								$page_data[3] = $data_link;
								$candidates_to_move[$group][] = $page_data;
							} else
								$result['dont-knows-where-to-go'][] = $page_data[1];
///								echo_log('dont_know_where_to_go', ' (' . $page_data[1] . ')');
						}
					}

					if (!!$candidates_to_move) {
//						debug($candidates_to_move, 'pages to move');
						foreach ($candidates_to_move as $old_group => $pages)
							foreach ($pages as $page_data) {
								$page_id = $page_data[0];
								$pa->update(array('group' => $page_data[2]), $page_id);
								$ua->changed($page_id, UPKIND_GROUP, $old_group);
								$data = array('link' => $page_data[1]);
								$result['pages-moved-to-group'][] = $page_data;
///								echo patternize(Loc::lget('page_changed_group'), $data) . '(ID#' . $page_data[2] . ')<br />';
							}
					} else
						$result['no-pages-to-move'] = true;
///						echo_log('no_pages_to_move');
				} else
						$result['pages-not-moved'] = true;
///					echo_log('pages_not_moved');

				foreach ($diff as $g_id) {
					$ua->changed($g_id, UPKIND_DELETED_GROUP, $author_id);
					$ga->delete($g_id);
					$result['groups-deleted'][] = $gii[$g_id];
///					echo patternize(Loc::lget('group_deleted'), $gii[$g_id][2]) . '<br />';
				}
			}


//			debug($gi, 'gi');

			$check = array();
			$page_titles = array();
			// is there anything to check?
			foreach ($links as $idx => &$block)
				foreach ($block as $link => &$data) { //            0      1     2         3
					if ($data[2] > 0) // size > 0 ?     // link => {group, title, size, description}
						$check[$link] = $data;
					$page_titles[$link] = $data[1];
				}

			if (count($check)) { // ok, there are smth to check
				$diff = array();
				$link_filter = join('", "', array_keys($check));
				$pa = PagesAggregator::getInstance();
				$d = $pa->fetch(array(
					'filter' => "`author` = $author_id and (`link` in (\"$link_filter\"))"
				, 'nocalc' => 1, 'desc' => 0
				, 'collumns' => '`id`, `link`, `size`, `group`, `title`'
				));
				if ($d['total']) { // there are smth in db, check it if size diff
					foreach ($d['data'] as $row) {
						$data = $check[$link = $row['link']]; // recently parsed data about page at @link

						// don't check this page later in this function (all pages
						// that are contained in $check array, but not contained in
						// database are assumed to be the "new" pages)
						unset($check[$link]); // page isn't "new"

						$page_id = intval($row['id']);

						$quoted = normalizeQuotedStr($row['title']);
						$new_quoted = normalizeQuotedStr($data[1]);
						if ($quoted <> $new_quoted) {
							$pa->update(array('title' => addslashes($new_quoted)), $page_id);
							$ua->changed($page_id, UPKIND_RENAMED, 0);
							$result['pages-changed-title'][] = array_merge($row, array('new-title' => $new_quoted));
						}

						if (intval($row['size']) <> $data[2])
							$diff[$page_id] = $link; // size changed, check it later for version change

						$old_group = intval($row['group']); // old page group global index (GUID)
						$old_group_i = isset($gi[$old_group]) ? $gi[$old_group] : null; // old group local index (index on author page)
						if ($old_group_i != $data[0]) { // local index of page was changed -> means that page moved to another group
							$new_group = array_search($data[0], $gi); // find GUID of new group
							if ($new_group) {
								$pa->update(array('group' => $new_group), $page_id);
								$ua->changed($page_id, UPKIND_GROUP, $old_group);
								$result['pages-changed-group'][] = array_merge($row, array('new-group' => $new_group));
///								echo patternize(Loc::lget('page_changed_group'), $row) . '(ID#' . $new_group . ')<br />';
							} else {
//								debug(array('old_i' => $old_group_i, 'new_i' => $data[0], 'old_id' => $old_group));
								$result['pages-changed-group'][] = array_merge($row, array('new-group' => 0));
///								echo 'Cant move page! Target group not found %)<br />';
							}
						}
					}
				}

				if (!!$diff) // updated pages
					$result['pages-updated'] = true;
///					echo_log('pages_updated');

				if (!!$check) { // new pages
					$result['pages-new'] = array();
///					echo_log('pages_new');
					foreach ($check as $link => &$data) { // each new page
						$idx = $data[0]; // group local id (IDX)
						$gid = array_search($idx, $gi); // group global id (GUID)
						$title = normalizeQuotedStr($data[1]);
						$pid = $pa->add(array(
							'author' => $author_id
						, 'group' => $gid
						, 'link' => $link
						, 'title' => addslashes($title)
						, 'description' => addslashes(htmlspecialchars($data[3], ENT_QUOTES))
						, 'size' => 0 // later it will be updated with version checker
						));
						$diff[$pid] = $link; // check its version
						$result['pages-new'][] = array('page-id' => $pid, 'link' => $link, 'title' => $title);
					}
				}

				if (!!$diff) { // there are updated or new pages
///					echo_log('pages_queue');
					$result['pages-queued-links'] = $diff;
					$fdiff = $this->queuePages($author_id, $diff); // queue this page for version check

					foreach ($fdiff as &$diff_data)
						$diff_data['title'] = $page_titles[$diff_data['link']];

					$result['pages-queued'] = $fdiff;
				} else
					$result['no-updates'] = true;
///					echo_log('no_updates');
			} else
				$result['no-updates'] = true;
///			echo_log('no_updates');

//			debug(array('g' => $g, 'gi' => $gi, 'gii' => $gii, 'gt' => $gt));

			return $result;
		}

		function checkGroup($group_id) {
			$result = array();
			$ga = GroupsAggregator::getInstance();
			$g = $ga->get($group_id, '`id`, `link`, `author`');
			if (uri_frag($g, 'id') != $group_id) return false;
			$author_id = intval($g['author']);

			$aa = AuthorsAggregator::getInstance();
			$a = $aa->get($author_id, '`link`');
			preg_match('/^[\/]*(.*?)[\/]*$/', $a['link'], $link);
			$link = $link[1] . '/' . $g['link'];
			$data = SAMLIBParser::getData($link, 'group');
			if ($data === false)
				return array('error' => Loc::lget('samlib_down'));
//				return echo_log('samlib_down');
			elseif ($data === null)
				return array('error' => Loc::lget('parse_method_unknown'));
//				return echo_log('parse_method_unknown');


			$ga->update(array('time' => time()), $group_id, true);

			$links = &$data['links'];
			$check = array();
			$page_titles = array();
			// is there anything to check?
			foreach ($links as $link => &$data) { //            0      1        2
				$page_titles[$link] = $data[0];
				if ($data[1] > 0) // size > 0 ?     // link => {title, size, description}
					$check[$link] = $data;
			}

			if (count($check)) { // ok, there are smth to check
				$link_filter = join('", "', array_keys($check));
				$ua = UpdatesAggregator::getInstance();
				$diff = array();
				$pa = PagesAggregator::getInstance();
				$d = $pa->fetch(array(
					'filter' => "`author` = $author_id and (`link` in (\"$link_filter\"))"
				, 'nocalc' => 1, 'desc' => 0
				, 'collumns' => '`id`, `link`, `size`, `group`, `title`'
				));
				if ($d['total']) { // there are smth in db, check it if size diff
					foreach ($d['data'] as $row) {
						$data = $check[$link = $row['link']]; // recently parsed data about group at @link
						unset($check[$link]); // don't check it later in this function

						$page_id = $row['id'];

						$quoted = normalizeQuotedStr($row['title']);
						$new_quoted = normalizeQuotedStr($data[0]);
						if ($quoted <> $new_quoted) {
							$pa->update(array('title' => addslashes($new_quoted)), $page_id);
							$ua->changed($page_id, UPKIND_RENAMED, 0);
							$result['pages-changed-title'][] = array_merge($row, array('new-title' => $new_quoted));
						}

						if (intval($row['size']) <> $data[1])
							$diff[$page_id] = $link; // check it

						$old_group = intval($row['group']);
						if ($group_id != $old_group) {
							$result['pages-changed-group'][] = array_merge($row, array('new-group' => $group_id));
///							echo patternize(Loc::lget('page_changed_group'), $row) . '<br />';
							$pa->update(array('group' => $group_id), $page_id);
							$ua->changed($page_id, UPKIND_GROUP, $old_group);
						}
					}
				}

				if (count($diff))
				;
//					$result['pages-updated'] = 1;
///					echo_log('pages_updated');

				if (count($check)) {
//					echo_log('pages_new');
					foreach ($check as $link => &$data) { // new pages
						$page_title = normalizeQuotedStr($data[0]);
						$pid = $pa->add(array(
							'author' => $author_id
						, 'group' => $group_id
						, 'link' => $link
						, 'title' => addslashes($page_title)
						, 'description' => addslashes(htmlspecialchars($data[2], ENT_QUOTES))
						, 'size' => 0 // later it will be updated with diff checker
						));
						$diff[$pid] = $link;
						$result['pages-new'][] = array('page-id' => $pid, 'link' => $link, 'title' => $page_title);
					}
				}

				if (count($diff)) {
//					echo_log('pages_queue');
					$result['pages-queued-links'] = $diff;
					$fdiff = $this->queuePages($author_id, $diff); // queue this page for version check

					foreach ($fdiff as &$diff_data)
						$diff_data['title'] = $page_titles[$diff_data['link']];

					$result['pages-queued'] = $fdiff;
				} else;
//					$result['pages-no-updates'] = 1;
//					echo_log('no_updates');
			} else;
//				$result['pages-no-updates'] = 1;
//				echo_log('no_updates');

			return $result;
		}

		function queuePages($author, $pages) {
			$a = AuthorsAggregator::getInstance();
			$d = $a->get($author, '`link`');
			$alink = $d['link'];
			$q = QueueAggregator::getInstance();

			$update = $q->queue(array_keys($pages), 0); // returns intersection (already_queued - pages)
			$idx = array_keys($pages);
			if (count($update)) {
				$diff = array_intersect($idx, $update);
///				echo_log('already_queued', ':');
				foreach ($diff as $page) {
					$link = $pages[$page];
///					echo "<a href=\"http://samlib.ru/$alink/$link\">$link</a><br />";
				}
///				echo '<br />';

				$idx = array_diff($idx, $update);
			}
///			echo_log('queued_pages', ':');
			$queue = array();
			foreach ($idx as $page) {
				$link = $pages[$page];
				$queue[] = array('page-id' => $page, 'link' => $link);
///				echo "<a href=\"http://samlib.ru/$alink/$link\">$link</a><br />";
			}
			return $queue;
		}

		function serveQueue($limit = 1, $timeout = 0) {
			$result = array();
			$t = time();

			$q = QueueAggregator::getInstance();
//			$q->dbc->debug = 1;
			// fetch not processed yet pages, or those, which process time elapsed (due to hung-up or time-break)
			$d = $q->fetch(array('desc' => 0
			, 'filter' => '(`state` = 0) or (`state` <> 0 and `updated` < ' . ($t - QUEUE_FAILTIME) . ')  limit ' . $limit
			, 'collumns' => '`id` as `0`, `page` as `1`'//, `state` as `2`, `updated` as `3`'
			));

			$done = 0;
			if ($left = $d['total']) {
///				echo '> ' . $d['total'] . ' pages waits for update...<br />';
				foreach ($d['data'] as $row)
					$u[intval($row[1])] = intval($row[0]);

				$s = $q->dbc->select('`pages` p, `authors` a'
				, 'p.`id` in (' . join(',', array_keys($u)) . ') and p.`author` = a.`id`'
				, 'p.`id`, p.`link`, p.`size`, a.`link` as `author`, p.`author` as `author-id`, p.`time`');
				$pages = $q->dbc->fetchrows($s);

//				debug2(array($left, count($pages)));
				if (count($u) > count($pages)) {
					$pids = fetch_field($pages, 'id');
					$pids = array_flip($pids);
					$drop = array();
					foreach ($u as $page => $queueID)
						if (!isset($pids[$page]))
							$drop[] = $queueID;

					$q->delete($drop);
//					debug2(array($u, $pids, $drop));
					$left -= count($drop);
					$left = ($left < 0) ? 0 : $left;
				}

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

//					echo "&gt; U@{$q_id}, ID#{$page}: {$row['link']}...<br />";
					$timings = $time;
					$size = $c->compare($page, "{$row['author']}/{$row['link']}", $timings);

					/* reconnect mysql DB (preventing "MySQL server has gone away") */
					$pa->dbc->reconnect();
					/* - */

					if ($size) {
						if (($old_size = intval($row['size'])) <> ($size = intval($size[2]))) {
							$pa->update(array('size' => $size, 'time' => $time), $page);

							$utype = ($old_size <= 0) ? UPKIND_ADDED : (($size <= 0) ? UPKIND_DELETED : UPKIND_SIZE);
							$ua->changed($page, $utype, $size - intval($row['size']));
//							$saveDir = PageUtils::getPageStorage($page);
							$result['diff'][] = array('page-id' => $page, 'link' => $row['link'], 'author' => $row['author-id'], 'oldsize' => $old_size, 'newsize' => $size);
//							echo " &nbsp;save to [$saveDir/$time.html]...<br />";
//							echo ' &nbsp;updated (' . $size . 'KB).<br />';
						} else {
							$result['no-diff'][] = array('page-id' => $page, 'author' => $row['author-id']);
						}
						$q->dbc->delete($q->TBL_DELETE, '`page` = ' . $page);
						$done++;
					} else {
						$deleted = isset($timings[404]);
						if ($deleted)
							$q->dbc->delete($q->TBL_DELETE, '`id` = ' . $q_id);
						else
							$q->dbc->update($q->TBL_INSERT, array('state' => QUEUE_PROCESS, 'updated' => time()), '`id` = ' . $q_id);
						$result[$deleted ? 'deleted' : 'fail'][] = array(
						'page-id' => $page, 'link' => $row['link'], 'author' => $row['author-id']
						, 'reason' => $timings['reason']
						, 'rcode' => $timings['RCode']
						);
//						echo_log('page_request_failed');
						$done++;
//						return $result;
					}
					if (($timeout > 0) && (time() - $t > $timeout)) {
						$result['left'] = $left - $done;
						return $result;
					}
				}
				$result['left'] = $left - $done;
			} else;
//				echo_log('nothing_to_update');

			$result['left'] = $left - $done;
			return $result;
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

		var $FETCH_PAGE = 20;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

		function getUpdates($page, $limit = 20) {
			$d = $this->fetch(array(
				'desc' => true
			, 'page' => $page - 1
			, 'pagesize' => $limit
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
					case UPKIND_DELETED_GROUP:
						$a[intval($row['value'])] = 1;
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
						$r[] = array('update' => $row['id'], 'id' => $gid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'group' => $gid
						, 'group_title' => $g[$gid]['title']
						, 'author' => $aid
						, 'fio' => $a[$aid]
						);
						break;
					case UPKIND_DELETED_GROUP:
						$gid = intval($row['page']);
						$aid = intval($row['value']);
						$r[] = array('update' => $row['id'], 'id' => $gid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'group' => $gid
						, 'group_title' => "{@GROUP#$gid}"
						, 'author' => $aid
						, 'fio' => $a[$aid]
						);
						break;
					case UPKIND_GROUP:
						$pid = intval($row['page']);
						$gid = $p[$pid]['group'];
						$aid = $p[$pid]['author'];
						$goid = intval($row['value']);
						$r[] = array('update' => $row['id'], 'id' => $pid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'title' => $p[$pid]['title']
						, 'group' => $gid
						, 'group_title' => $g[$gid]['title']
						, 'old_id' => $goid
						, 'old_title' => ($t = uri_frag($g, $goid, 0, 0)) ? $t['title'] : "{@GROUP#$goid}"
						, 'author' => $aid
						, 'fio' => $a[$aid]
						);
						break;
					default: // page updates
						$pid = intval($row['page']);
						$aid = $p[$pid]['author'];
						$gid = $p[$pid]['group'];
						$r[] = array('update' => $row['id'], 'id' => $pid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'title' => $p[$pid]['title']
						, 'group' => $gid
						, 'group_title' => isset($g[$gid]) ? $g[$gid]['title'] : "{@GROUP#$gid}"
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

		function getAuthorUpdates($author) {
			$d = $this->fetch(array(
				'desc' => true
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
					case UPKIND_DELETED_GROUP:
						$a[intval($row['value'])] = 1;
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
				$a = $aa->get($author, '`fio`');
				$fio = $a['fio'];

				foreach ($d['data'] as &$row) {
					$aid = 0;
					switch ($kind = $row['kind']) {
					case UPKIND_INLINE: // group updates
						$gid = intval($row['page']);
						$aid = $g[$gid]['author'];
						if ($aid != $author)
							continue;

						$r[] = array('id' => $gid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'group' => $gid
						, 'group_title' => $g[$gid]['title']
						, 'author' => $aid
						, 'fio' => $fio
						);
						break;
					case UPKIND_DELETED_GROUP:
						$aid = intval($row['value']);
						if ($aid != $author)
							continue;
						$gid = intval($row['page']);
						$r[] = array('id' => $gid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'group' => $gid
						, 'group_title' => "{@GROUP#$gid}"
						, 'author' => $aid
						, 'fio' => $fio
						);
						break;
					case UPKIND_GROUP:
						$pid = intval($row['page']);
						$aid = $p[$pid]['author'];
						if ($aid != $author)
							continue;
						$gid = $p[$pid]['group'];
						$goid = intval($row['value']);
						$r[] = array('id' => $pid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'title' => $p[$pid]['title']
						, 'group' => $gid
						, 'group_title' => $g[$gid]['title']
						, 'old_id' => $goid
						, 'old_title' => ($t = uri_frag($g, $goid, 0, 0)) ? $t['title'] : "{@GROUP#$goid}"
						, 'author' => $aid
						, 'fio' => $fio
						);
						break;
					default: // page updates
						$pid = intval($row['page']);
						$aid = $p[$pid]['author'];
						if ($aid != $author)
							continue;
						$gid = $p[$pid]['group'];
						$r[] = array('id' => $pid, 'kind' => $kind, 'value' => $row['value'], 'time' => $row['time']
						, 'title' => $p[$pid]['title']
						, 'group' => $gid
						, 'group_title' => isset($g[$gid]) ? $g[$gid]['title'] : "{@GROUP#$gid}"
						, 'author' => $aid
						, 'fio' => $fio
						);
						break;
					}
				}
			}
			return array('data' => $r, 'total' => $d['total']);
		}
	}
