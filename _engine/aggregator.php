<?php
	require_once 'dbengine.php';
	require_once 'common.php';

	class Aggregator {
		protected static $instances = array();
		var $dbc = null;
		var $TBL_FETCH = '';
		var $TBL_INSERT = '';
		var $TBL_DELETE = '';
		var $COL_ID     = '`id`';
		var $collumns = array(
			'`id` int auto_increment null'
		, '`time` int not null'
		, 'primary key (`id`)'
		);

		var $FETCH_PAGE = 3;

		protected function __construct($args = null) {
			$this->dbc = msqlDB::o();
		}

		public function add(array $data) {
			if (!isset($data['time']))
				$data['time'] = time();
			$s = $this->dbc->insert($this->TBL_INSERT, $data, true);
			return $s ? intval(@mysql_result($s, 0)) : 0;
		}

		public function update(array $data, $id, $low = false) {
			if (!isset($data['time']))
				$data['time'] = time();
			$s = $this->dbc->update($this->TBL_INSERT, $data, array('id' => $id), $low);
			return $s ? $id : 0;
		}

		public function delete($id) {
			return $this->dbc->delete($this->TBL_DELETE, $this->COL_ID . (
					!is_array($id)
				? ' = ' . intval($id) . ' limit 1'
				: ' in (' . join(',', $id) . ') limit ' . count($id)
			));
		}

		public function fetch(array $params) {
			//$page, $pagesize, $collumns = null, $desc = true, $filter = ''
			$numrows = isset($params['pagesize']) ? $params['pagesize'] : 0;
			$page = isset($params['page']) ? intval($params['page']) : 0;
			$limit = ($numrows > 1) ? ' limit ' . ($page * $numrows)  . ', ' . $numrows : ($numrows == 1 ? ' limit 1' : '');
			$desc = isset($params['desc']) ? ($params['desc'] ? ' desc' : $params['desc']) : 0;
			$order = isset($params['order']) ? $params['order'] : '`time`';
			$order = ($desc !== 0) ? " order by $order $desc" : '';
			$nocalc = isset($params['nocalc']) ? $params['nocalc'] : 0;
			$filter = isset($params['filter']) ? $params['filter'] : '';
			$collumns = isset($params['collumns']) ? $params['collumns'] : '*';
			$countrows = $numrows != 1 && !$nocalc;

			$s = $this->dbc->select(
					$this->TBL_FETCH
				, "$filter{$order}$limit"
				, $countrows ? "SQL_CALC_FOUND_ROWS $collumns" : $collumns
			);

			if ($countrows && $s) {
				$s1 = $this->dbc->query('SELECT FOUND_ROWS()');
				$t  = @mysql_fetch_row($s1);
				$total  = $t[0];
			} else
				$total = $s ? $this->dbc->rows($s) : 0;

			$this->data = array('result' => $s, 'data' => $this->dbc->fetchrows($s), 'total' => intval($total));
			$link = &$this->data;
			return $link;
		}

		public function get($id, $cols = '*') {
			$s = $this->dbc->select($this->TBL_FETCH, $this->COL_ID . (
					!is_array($id)
				? ' = ' . intval($id) . ' limit 1'
				: ' in (' . join(',', $id) . ') limit ' . count($id)
			), $cols);
			return $s ? (is_array($id) ? $this->dbc->fetchrows($s) : @mysql_fetch_assoc($s)) : array();
		}

		public function generatePageList($page, $last, $root = '', $params = '') {
			$first = 1;
			$prev = $page > $first ? $page - 1 : $first;
			$next = $page < $last ? $page + 1 : $last;
			$hasprev = $prev < $page;
			$hasnext = $next > $page;
			$link = array();
			$link[] = '<li class="big' . ($hasprev ? '' : ' disabled') . '"><a' . ($hasprev ? ' href="/' . $root . 'page/' . $prev . $params . '"' : '') . '>Назад</a></li>';
			if ($first < $prev) $link[] = '<li><a href="/' . $root . 'page/' . $first . $params . '">' . $first . '</a></li>';
			if ($first < $prev - 1) $link[] = '<li class="disabled"><a>..</a></li>';
			if ($hasprev) $link[] = '<li><a href="/' . $root . 'page/' . $prev . $params . '">' . $prev . '</a></li>';
			$link[] = '<li class="active"><a>'.$page.'</a></li>';
			if ($hasnext) $link[] = '<li><a href="/' . $root . 'page/' . $next . $params . '">' . $next . '</a></li>';
			if ($last  > $next + 1) $link[] = '<li class="disabled"><a>..</a></li>';
			if ($last  > $next) $link[] = '<li><a href="/' . $root . 'page/' . $last . $params . '">' . $last . '</a></li>';
			$link[] = '<li class="big' . ($hasnext ? '' : ' disabled') . '"><a' . ($hasnext ? ' href="/' . $root . 'page/' . $next . $params . '"' : '') . '>Вперед</a></li>';

			return join(PHP_EOL, $link) . PHP_EOL;
		}

		public function init() {
			return $this->dbc->create_table($this->TBL_INSERT, $this->collumns);
		}

	}

?>