<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

	define('AUTHORS_UPDATE_PER_BATCH', 5);
	define('GROUPS_UPDATE_PER_BATCH', 10);

	define('AUTHOR_CHECK_MININTERVAL', 30); // check not oftener, than once per 30 minutes
	define('AUTHOR_CHECK_MAXINTERVAL', 60 * 6); // check at least once per 6 hours
	define('USEFUL_INTERVAL', 60 * 60 * 24 * 61); // two month

	class HistoryAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'history';
		var $TBL_INSERT = 'history';
		var $TBL_DELETE = 'history';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`user` int not null'
		, '`page` int not null'
		, '`lastseen` int null default 0'
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

		function fetchUpdates($uid, $traces = null, $colls = '*', $filter = 1) {
			if ($traces) {
				$d = $this->fetch(array('nocalc' => 1, 'desc' => 0
				, 'filter' => '`id` in (' . join(',', $traces) . ')'
				, 'collumns' => '`page` as `0`'
				));
				if (!$d['total']) return array();

				$p = array();
				foreach ($d['data'] as &$row)
					$p[] = intval($row[0]);

				$p = join(',', $p);

				$q = "p.`id` in ($p) and p.`id` = h.`page` and h.`trace` = $filter";
			} else
				$q = "p.`id` = h.`page` and h.`user` = $uid and h.`trace` = $filter and p.`size` <> h.`size`";

			$s = $this->dbc->select('pages p, history h'
			, $q
			, 'h.`id` as `0`, h.`page`, p.`description`, p.`size`, p.`time`, p.`title`, h.`size` as `size_old`, h.`time` as `time_old`'
			);
			$f = $this->dbc->fetchrows($s);
			$s = array();
			foreach ($f as &$row)
				$s[intval($row[0])] = $row;

			return $s;
		}

		function upToDate($idx, $time = 0) {
			$g = 'greatest(h.`time`, ' . ($time ? $time : time()) . ')';
			$this->dbc->update('history` `h`, `pages` `p'
			, "h.`size` = p.`size`, h.`lastseen` = $g, h.`time` = $g"
			, 'h.`id` in (' . join(',', $idx) . ') and h.`page` = p.`id`'
			, true);
		}

		function markTrace($idx, $trace = 0) {
			$this->dbc->update('history'
			, '`trace` = ' . $trace . ', `time` = ' . time()
			, '`id` in (' . join(',', $idx) . ')'
			);
		}

		function authorsToUpdate($uid, $force = 0, $all = 0, $trace = 1) {
			$t = time() - ($force ? ($all ? -100 : 5) : AUTHOR_CHECK_MININTERVAL * 60); // 60 minutes
			$all = $all ? 100 : AUTHORS_UPDATE_PER_BATCH;
			if ($uid)
				$s = $this->dbc->select('`history` h, `pages` p, `authors` a'
				, 	"h.`user` = $uid and h.`trace` = $trace"
					. ' and h.`page` = p.`id` and a.`id` = p.`author` and a.`time` < ' . $t
					. ' group by a.`id` order by a.`time` limit ' . $all
				, 'a.`id` as `0`'
				);
			else {
				$now = time();
				$flag = "(($now - `time`) / 60) - update_freq";
				$filter = $force ? "1" : "$flag > 0";
				$s = $this->dbc->select('authors'
				, "$filter order by $flag desc limit " . $all
				, '`id` as `0`'
				);
			}
			$a = array();
			if ($s)
				foreach($this->dbc->fetchrows($s) as $row)
					$a[] = intval($row[0]);

			return $a;
		}

		function groupsToUpdate($force = 0, $limit = 0) {
			$a = array();
			$dbc = msqlDB::o();
			$t = time() - ($force ? 1 : 60 * 60); // 60 minutes
			$a_ids = $this->authorsToUpdate(0, $force, $limit);
			if (!$a_ids) return $a;
			$filter = join(', ', $a_ids);
			$limit = $limit ? $limit : GROUPS_UPDATE_PER_BATCH;
			$s = $dbc->select('groups'
			, "author in ($filter) and `time` < $t and `link` <> '' and `link` not like '/%'"
			. ' order by `time` limit ' . $limit
			, '`id` as `0`'
			);
			if ($s)
				foreach($dbc->fetchrows($s) as $row)
					$a[] = intval($row[0]);

			return $a;
		}

		function authorGroupsToUpdate($authorID, $force = 0, $limit = 0) {
			$a = array();
			$dbc = msqlDB::o();
			$t = time() - ($force ? 1 : 60 * 60); // 60 minutes
			$limit = $limit ? "limit $limit" : '';
			$s = $dbc->select('groups'
			, "author = $authorID and `time` < $t and `link` <> '' and `link` not like '/%'"
			. ' order by `time` ' . $limit
			, '`id` as `0`'
			);
			if ($s)
				foreach($dbc->fetchrows($s) as $row)
					$a[] = intval($row[0]);

			return $a;
		}

		function tracePages($author, $page = 0) {
			$s = $this->dbc->select('`pages`', (!$page) ? "`author` = $author" : "`id` = $page", '`id` as `0`');
			$idx = array(); // author pages
			if ($s) {
				$f = $this->dbc->fetchrows($s);
				foreach ($f as &$row)
					$idx[] = intval($row[0]);
			}
			return $idx;
		}

		function traceNew($author, $uid, $page = 0, $trace = 0, $new_only = 1) {
			$idx = $this->tracePages($author, $page); // fetch all author pages or be sure, that ID#page page exists
			$p = array(); // traced pages
			$t = array(); // traced flag
			// fetch page tracing flags for current user
			$d = $this->fetch(array('nocalc' => 1, 'desc' => 0, 'filter' => "`user` = $uid", 'collumns' => '`page` as `0`, `trace` as `1`, `id` as `2`'));
			if ($d['total'])
				foreach ($d['data'] as &$row) {
					$page_id = intval($row[0]);
					// if we want to trace pages, and page already is traced, add to exclude list
					if (($trace == intval($row[1])) || $new_only)
						$p[] = $page_id;
					else // else remember page, that already presents in list
						$t[$page_id] = intval($row[2]); // key-access will be faster, than array_search proubably
				}

			$diff = array_diff($idx, $p); // from all pages (or with specified page) exclude all pages, that already is traced
			if (count($diff)) // there are pages, that not traced yet (or added to list, but has "trace = 0" flag state
				$this->traceHistory($uid, $diff, $trace, $t);
			return $diff;
		}

		function traceHistory($uid, $idx, $trace, $in_list) {
			foreach ($idx as $page_id)
				if (!isset($in_list[$page_id])) // no page in trace list
					$this->add(array('user' => $uid, 'page' => $page_id, 'trace' => $trace, 'time' => time()));
				else // page already in list, update flag state only
					$this->update(array('trace' => $trace, 'time' => time()), $in_list[$page_id]);
		}


		function calcCheckFreq() {
			$a = AuthorsAggregator::getInstance();
			$ids = $a->idsOf('1', false);

			if (!!$ids) {
				$u = array();
				$now = time();
				$useful = $now - USEFUL_INTERVAL;
				foreach ($ids as $author_id) {
					$s = $this->dbc->query("
						select max(t1.time) as `time`, count(t1.id) as `total` from (
							select t.time, t.id from (
										select u.id, u.time, (-u.page) as `page` from updates u, groups g
										where u.time >= $useful and (u.kind = 3 and g.id = u.page and g.author = $author_id)
							union select u.id, u.time, u.page from updates u, pages p
										where u.time >= $useful and (u.kind not in (3, 4) and p.id = u.page and p.author = $author_id)
							union select u.id, u.time, (-u.page) as `page` from updates u
										where u.time >= $useful and (u.kind = 4 and u.value = $author_id)) t
							group by `page`, `time`
							) t1
					");
					$row = @mysql_fetch_assoc($s);
					$last_update = intval($row['time']);
					$updates = min(1000, intval($row['total']));

					$since_last_update = $now - $last_update;
					$update_freq = ($since_last_update > USEFUL_INTERVAL) ? $since_last_update / USEFUL_INTERVAL : 1;
					$u[$author_id] = array(0 => $updates / $update_freq, 1 => $author_id, 2 => $updates, 3 => $update_freq);
				}

				$updated_authors = 0;
				foreach ($u as &$data) {
					$updates = intval($data[2]);
					if ($updates) $updated_authors++;
				}

				$max = 0;
				do {
					$sum = 0;
					$avg = 0;
					$mold = $max;
					$max = 0;
					foreach ($u as &$data) {
						$updates = intval($data[2]);
						$sum += $updates;
						if ($max < $updates) $max = $updates;
					}
					$avg = max(1, $sum / $updated_authors);

					$border = $avg + ($max - $avg) / 2;
					$mod = false;
					foreach ($u as &$data) {
						if ($data[2] > $border) {
							$mod = true;
							$data[2] = $border + ($data[2] - $border) / 2;
						}
					}
				} while ($mold != $max);

				foreach ($u as &$data)
					$data[0] = $data[2] / $data[3];

				usort ($u, "array_comparator");
				$last = count($u) - 1;
				$c_max = $u[0][0];
				$c_min = $u[$last][0];
				$time_denom = (AUTHOR_CHECK_MAXINTERVAL - AUTHOR_CHECK_MININTERVAL) / ($c_max - $c_min);
				foreach ($u as &$author)
					$author[2] = intval(($c_max + $c_min - $author[0]) * $time_denom) + AUTHOR_CHECK_MININTERVAL;

//				debug2(array(fetch_field($u, 0), $u));
				foreach ($u as $update_data)
					$a->update(array('update_freq' => $update_data[2]), $update_data[1], true, false);

//				debug2(array(AUTHOR_CHECK_MAXINTERVAL, AUTHOR_CHECK_MININTERVAL, $time_denom, $u));
			}
		}

	}

	function array_comparator($a1, $a2) {
		if ($a1[0] == $a2[0]) return 0;
		return ($a1[0] > $a2[0]) ? -1 : 1;
	}
