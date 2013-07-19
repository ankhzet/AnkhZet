<?php
	require_once 'aggregator.php';

	class VisitorsAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'visitors';
		var $TBL_INSERT = 'visitors';
		var $TBL_DELETE = 'visitors';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`ip` int not null'
		, '`ua_string` varchar(255) not null'
		, '`type` int null default 0'
		, '`ua` int null default 0'
		, '`os` int null default 0'
		, '`cast` float null default 0.0'
		, '`uri` varchar(255) null'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 20;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

		function addVisitor($type, $ua, $os, $cast) {
			return $this->add(array(
				'type' => $type
			, 'ip' => ip2long($_SERVER['REMOTE_ADDR'])
			, 'user' => User::get()->ID()
			, 'ua' => $ua
			, 'os' => $os
			, 'cast' => $cast
			, 'uri' => $_SERVER['REQUEST_URI']
			, 'ua_string' => $_SERVER['HTTP_USER_AGENT']
			));
		}

	}

?>