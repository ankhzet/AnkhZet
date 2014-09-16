<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'aggregator.php';

	class AuthorsAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'authors';
		var $TBL_INSERT = 'authors';
		var $TBL_DELETE = 'authors';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`link` varchar(255) null'
		, '`fio` varchar(255) null'
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

	class GroupsAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'groups';
		var $TBL_INSERT = 'groups';
		var $TBL_DELETE = 'groups';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`author` int not null'
		, '`group` int not null'
		, '`link` varchar(255) null'
		, '`title` varchar(255) null'
		, '`description` text null'
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