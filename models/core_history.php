<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

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
			$d = $this->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => $traces ? '`id` in (' . join(',', $traces) . ')' : "`user` = $uid and `trace` = 1"
			, 'collumns' => '`page` as `0`'
			));
			if (!$d['total']) return array();

			$p = array();
			foreach ($d['data'] as &$row)
				$p[] = intval($row[0]);

			$s = $this->dbc->select('pages p, history h'
			, 'p.`id` in (' . join(',', $p) . ') and p.`id` = h.`page` and h.`trace` = ' . $filter . ($traces ? '' : ' and p.`size` <> h.`size`')
			, 'h.`id` as `0`, h.`page`, p.`description`, p.`size`, h.`size` as `size_old`, p.`time`, p.`title`');

			$f = $this->dbc->fetchrows($s);
			$s = array();
			foreach ($f as &$row)
				$s[intval($row[0])] = $row;

			return $s;
		}

		function upToDate($idx) {
			$this->dbc->update('history` `h`, `pages` `p'
			, 'h.`size` = p.`size`, h.`lastseen` = p.`time`, h.`time` = ' . time()
			, 'h.`id` in (' . join(',', $idx) . ') and h.`page` = p.`id`'
			);
		}

		function dontTrace($idx) {
			$this->dbc->update('history'
			, '`trace` = 0'
			, '`id` in (' . join(',', $idx) . ')'
			);
		}

		function authorsToUpdate($uid, $force = 0) {
			$t = time() - ($force ? 60 : 60 * 30); // 30 minutes
			if ($uid)
				$s = $this->dbc->select('`history` h, `pages` p, `authors` a'
				, 'h.`user` = ' . $uid . ' and h.`trace` = 1 and h.`page` = p.`id` and a.`id` = p.`author` and a.`time` < ' . $t . ' group by a.`id`'
				, 'a.`id` as `0`'
				);
			else
				$s = $this->dbc->select('`authors` a'
				, 'a.`time` < ' . $t
				, 'a.`id` as `0`'
				);
			$a = array();
			if ($s)
				foreach($this->dbc->fetchrows($s) as $row)
					$a[] = intval($row[0]);

			return $a;
		}

		function tracePages($author) {
			$s = $this->dbc->select('`pages`', '`author` = ' . $author, '`id` as `0`');
			$idx = array(); // author pages
			if ($s) {
				$f = $this->dbc->fetchrows($s);
				foreach ($f as &$row)
					$idx[] = intval($row[0]);
			}
			return $idx;
		}

		function traceNew($author, $uid) {
			$idx = $this->tracePages($author);
			$p = array(); // traced pages
			$d = $this->fetch(array('nocalc' => 1, 'desc' => 0, 'filter' => '`user` = ' . $uid, 'collumns' => '`page` as `0`'));
			if ($d['total'])
				foreach ($d['data'] as &$row)
					$p[] = intval($row[0]);

			$diff = array_diff($idx, $p);
			if (count($diff)) // there are pages, that not traced yet
				$this->traceHistory($uid, $diff);
			return $diff;
		}

		function traceHistory($uid, $idx) {
			foreach ($idx as $page_id)
				$this->add(array('user' => $uid, 'page' => $page_id, 'time' => 0));
		}

	}
?>