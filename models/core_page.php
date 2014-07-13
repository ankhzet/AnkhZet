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

	class PagesCompositionAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'pages p, `groups` g, `authors` a, pages_composition c';
		var $TBL_FETCH2 = 'pages_composition c';
		var $TBL_INSERT = 'pages_composition';
		var $TBL_DELETE = 'pages_composition';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`page` int not null'
		, '`composition` int not null'
		, '`order` int not null'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $COL_ID     = 'c.`id`';
		var $COL_IDDEL  = '`id`';
		var $FETCH_PAGE = 5;

		static public function getInstance($args = null) {
			if (!isset(self::$instance))
				self::$instance = new self($args);

			return self::$instance;
		}

		public function fetch(array $params) {
			$params['filter'] = 'c.`page` = p.`id` and p.`group` = g.`id` and p.`author` = a.`id`' . (isset($params['filter']) ? " and ({$params['filter']})" : '');
			$params['order'] = isset($params['order']) ? str_replace('`time`', 'c.`time`', $params['order']) : 'c.`time`';
			$params['desc'] = ($params['desc'] === 0) ? 0 : false;
			return parent::fetch($params);
		}

		function genComposition($pages) {
			if (!is_array($pages)) $pages = array(intval($pages));
			$base = intval(array_shift($pages));
			$compositionID = $this->add(array('page' => $base, 'order' => 0));
			if (!$compositionID)
				return false;

			array_unshift($pages, $base);

			foreach ($pages as $idx => $pageID)
				if ($pageID != $base)
					$this->add(array('page' => $pageID, 'order' => $idx, 'composition' => $compositionID));
				else
					$this->update(array('composition' => $compositionID), $compositionID, true);

			return $compositionID;
		}

		function orderInComposition($comID, $pageID) {
			$s = $this->dbc->select($this->TBL_FETCH2, "`composition` = $comID and `page` = $pageID", '`id` as `0`, `order` as `1`');
			$r = $s ? @mysql_fetch_row($s) : array();
			$id = intval($r[0]);
			return $id ? intval($r[1]) : false;
		}

		function compose($comID, $pageID, $insertAt = -1) {
			$this->remove($comID, array(intval($pageID)));

			if ($insertAt < 0) {
				$s = $this->dbc->select($this->TBL_FETCH2, "`composition` = $comID", 'count(`id`) as `0`, max(`order`) as `1`');
				$r = $s ? @mysql_fetch_row($s) : array();
				$count = intval($r[0]);
				$insertAt = $count ? intval($r[1]) + 1 : 0;
			}

			if (!$insertAt) {
				$s = $this->dbc->select($this->TBL_FETCH2, "`composition` = $comID and `order` = 0", '`title` as `0`');
				$title = $s ? @mysql_result($s, 0) : 0;
			}
			else
				$title = null;

			if ($inserted = $this->add(array('page' => $pageID, 'title' => $title, 'order' => $insertAt, 'composition' => $comID))) {
				$this->dbc->update(
						$this->TBL_INSERT
					, '`order` = `order` + 1'
					, "(`composition` = $comID) and (`order` >= $insertAt) and (`id` != $inserted)"
					, true
				);
			} else
				return false;

			return true;
		}

		function remove($comID, $pageIDs) {
			if (!is_array($pageIDs))
				return $this->remove($comID, array(intval($pageIDs)));

			$pages = $this->fetchPages($comID);

			$order = fetch_field($pages, 'order', 'page');
			$ids = fetch_field($pages, 'id', 'page');
			$titles = fetch_field($pages, 'composition:title', 'page');

			$remove = array();
			$oldorder = $order;
			foreach ($pageIDs as $id) {
				if (isset($ids[$id])) $remove[$id] = $ids[$id];
				unset($order[$id]);
				unset($ids[$id]);
			}

			if (!count($remove)) return false;

			asort($order);
			$reordered = array();
			$idx = 0;
			foreach ($order as $page => $old)
				$reordered[$page] = $idx++;

			$oldIndex = intval(array_search(0, $oldorder));
			$newIndex = intval(array_search(0, $reordered));

			if ($newIndex && ($oldIndex != $newIndex)) {
				$this->update(array('title' => $titles[$oldIndex]), $ids[$newIndex]);
			}
			$this->delete($remove);
			foreach ($reordered as $page => $order)
				$this->update(array('order' => $order), $ids[$page]);

			return true;
		}

		function inComposition($pageID) {
			$fetch = array();
			$data = $this->fetch(array('nocalc' => true,
				'filter' => "`page` = $pageID"
			, 'desc' => 0
			, 'collumns' => '`composition` as `0`'
			));
			if ($data['total']) {
				foreach ($data['data'] as $row) {
					$id = intval($row[0]);
					$com = $this->fetch(array('nocalc' => true, 'desc' => 0, 'pagesize' => 1
					, 'filter' => "`composition` = $id and `order` = 0"
					, 'collumns' => '`composition`, c.`title`, p.`author`, `fio`'
					));
					$fetched[] = $com['data'][0];
				}
			}
			return $fetched;
		}

		function fetchPages($id) {
			$data = $this->fetch(array('nocalc' => true, 'desc' => true, 'order' => '`order`',
				'filter' => "`composition` = $id"
			, 'collumns' => 'c.`id`, c.`title` as `composition:title`, `page`, p.`title`, `order`, p.`group`, g.`title` as `group:title`, p.`author`, a.`fio` as `fio`'
			));
			return $data['data'];
		}
	}

	class GrammarAggregator extends Aggregator {
		static $instance = null;
		var $TBL_FETCH  = 'grammar';
		var $TBL_INSERT = 'grammar';
		var $TBL_DELETE = 'grammar';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`user` int not null'
		, '`page` int not null'
		, '`zone` varchar(60) not null'
		, '`range` varchar(50) not null'
		, '`replacement` varchar(255) not null'
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
