<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

	class NewsAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'news';
		var $TBL_INSERT = 'news';
		var $TBL_DELETE = 'news';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`title` varchar(255) not null'
		, '`source` varchar(255) null'
		, '`content` text not null'
		, '`preview` varchar(255) null'
		, '`views` int null default 0'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 10;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

	}
?>