<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

	define('AUTHORS_UPDATE_PER_BATCH', 5);
	define('GROUPS_UPDATE_PER_BATCH', 10);

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
			$t = time() - ($force ? ($all ? -100 : 5) : 60 * 60); // 60 minutes
			$all = $all ? 100 : AUTHORS_UPDATE_PER_BATCH;
			if ($uid)
				$s = $this->dbc->select('`history` h, `pages` p, `authors` a'
				, 	"h.`user` = $uid and h.`trace` = $trace"
					. ' and h.`page` = p.`id` and a.`id` = p.`author` and a.`time` < ' . $t
					. ' group by a.`id` order by a.`time` limit ' . $all
				, 'a.`id` as `0`'
				);
			else
				$s = $this->dbc->select('authors'
				, '`time` < ' . $t . ' order by `time` limit ' . $all
				, '`id` as `0`'
				);
			$a = array();
			if ($s)
				foreach($this->dbc->fetchrows($s) as $row)
					$a[] = intval($row[0]);

			return $a;
		}

		function groupsToUpdate($force = 0) {
			$dbc = msqlDB::o();
			$t = time() - ($force ? 5 : 60 * 60); // 60 minutes
			$s = $dbc->select('groups'
			, 	'`time` < ' . $t . ' and `link` <> "" and `link` not like "/%"'
				. ' order by `time` limit ' . GROUPS_UPDATE_PER_BATCH
			, '`id` as `0`'
			);
			$a = array();
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

	}

