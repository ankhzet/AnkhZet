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

		function fetchUpdates($uid, $traces = null, $colls = '*') {
			$d = $this->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => $traces ? '`id` in (' . join(',', $traces) . ')' : "`user` = $uid"
			, 'collumns' => '`page` as `0`'
			));
			if (!$d['total']) return array();

			$p = array();
			foreach ($d['data'] as &$row)
				$p[] = intval($row[0]);

			$s = $this->dbc->select('pages p, history h'
			, 'p.`id` in (' . join(',', $p) . ') and p.`id` = h.`page`' . ($traces ? '' : ' and p.`size` <> h.`size`')
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
	}
?>