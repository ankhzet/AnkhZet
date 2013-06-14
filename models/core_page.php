<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

	class PagesAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'pages';
		var $TBL_INSERT = 'pages';
		var $TBL_DELETE = 'pages';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`author` int not null'
		, '`group` int not null'
		, '`link` varchar(255) not null'
		, '`title` varchar(255) not null'
		, '`description` text not null'
		, '`size` int null default 0'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 20;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

	}
?>