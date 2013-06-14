<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

	class CommentsAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'comments_';
		var $TBL_INSERT = 'comments_';
		var $TBL_DELETE = 'comments_';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`user` int not null'
		, '`link` int not null'
		, '`comment` varchar(255) not null'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 10;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

		protected function __construct($args = null) {
			$this->dbc = msqlDB::o();
			$this->TBL_FETCH .= $args;
			$this->TBL_INSERT .= $args;
			$this->TBL_DELETE .= $args;
		}

	}
?>