<?php
	if (ROOT == 'ROOT') die('oO');

	require_once ROOT . '/_engine/aggregator.php';

	class StaticsAggregator extends Aggregator {
		static $instance = null;
		var $FETCH_PAGE = 10;
		var $TBL_FETCH  = 'statics';
		var $TBL_INSERT = 'statics';
		var $TBL_DELETE = 'statics';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`title` varchar(255) not null'
		, '`link` varchar(255) not null'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

	}
?>