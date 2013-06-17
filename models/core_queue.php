<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

	define('QUEUE_NEW', 0);
	define('QUEUE_PROCESS', 1);
	define('QUEUE_FAILTIME', 60 * 10);//60 * 60); // 1 hour

	class QueueAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'queue';
		var $TBL_INSERT = 'queue';
		var $TBL_DELETE = 'queue';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`page` int not null'
		, '`state` int null default 0'
		, '`updated` int null default 0'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 10;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

		function checkQueued($check) {
			$d = $this->fetch(array(
				'filter' => '`page` in (' . join(',', $check) . ')'
			, 'collumns' => '`page` as `0`, `id` as `1`'
			, 'nocalc' => 1, 'desc' => 0
			));
			$idx = array();
			if ($d['total'])
				foreach ($d['data'] as $row)
					$idx[intval($row[0])] = intval($row[1]);

			return $idx;
		}

		function queue($pages, $state = QUEUE_NEW) {
			$idx = $this->checkQueued($pages); // {page => queue_id}, ... , {}
			$keys = array_flip($idx); // {queue_id => page}
			$diff = array_diff($pages, $keys); // need to queue but not queued yet
			$t = time();
			if ($c = count($diff))
				foreach ($diff as $page)
					$this->dbc->insert($this->TBL_INSERT, array('page' => $page, 'state' => $state, 'updated' => $t, 'time' => $t), false);

			$u = array();
			$in = array_intersect($keys, $pages);
			if (count($in)) {
				$d = $this->fetch(array('nocalc' => 1, 'desc' => 0
				, 'filter' => '(`id` in (' . join(',', array_keys($in)) . ')) and ((`state` = 0) or (`state` <> 0 and `updated` < ' . ($t - QUEUE_FAILTIME) . '))'
				, 'collumns' => '`id` as `0`, `page` as `1`'
				));
				if ($d['total']) {
					foreach ($d['data'] as $row)
						$u[intval($row[0])] = intval($row[1]);

					$s = $this->dbc->update(
						$this->TBL_INSERT
					, array('state' => $state, 'updated' => $t)
					, '`id` in (' . join(',', array_keys($u)) . ')'
					);
				}
			}

			return array_diff(array_diff($pages, $u), $diff);
		}
	}
?>