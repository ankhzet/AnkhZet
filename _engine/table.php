<?php
	require_once 'dbengine.php';


	class DBTableRenderer {
		const DEF_PAGESIZE = 30;
		const T_ID         = 'id';

		var $maxrows;
		var $select;
		var $rows;
		var $array;
		var $link;
		var $order;
		var $dir;
		public $params;

		function columns() {
			return array(self::T_ID);
		}

		function colWidths() {
			return array(self::T_ID => 5);
		}

		function colTitles() {
			return array(self::T_ID => 'ID');
		}

		public function __construct($link, $tables, $fields = '', $colnames = '*') {
			$this->order = $this->params['order'];
			$this->dir   = $this->params['dir'] == 'desc' ? 'desc' : '';
			$this->pagesize = intval($this->params['pagesize']) > 1 ? intval($this->params['pagesize']) : self::DEF_PAGESIZE;
			$this->page = intval($this->params['page']) ? intval($this->params['page']) : 1;
			if (isset($this->order) && ($this->order != '')) $fields .= ' ORDER BY ' . $this->order . ' ' . $this->dir;
			$this->link = $link;
			$this->maxrows = $this->pagesize;
			$db = msqlDB::o();
			$this->select = $db->select($tables, $fields, $colnames);
			$db->check($this->select, __FILE__);
			$this->rows   = $db->rows($this->select);
			$this->array  = $db->fetchrows($this->select);
		}

		function prepareRow($column, $data) {
			return $data[$column];
		}

		function render() {
			$c = $this->columns();
			$w = $this->colWidths();
			$t = $this->colTitles();
			$from = ($this->page - 1) * $this->maxrows;
			$to = ($to = ($from + $this->maxrows)) > $this->rows ? $this->rows : $to;

			echo '<table id="datatable">' . PHP_EOL;
			$this->renderHeader();

			$r = '';
			$odd = array(false=>'', true=>' class=oddline');
			$o   = false;
			for ($i = $from; $i < $to; $i++) {
				$row = $this->array[$i];
				if ($o = !$o) $res .= '<tr class="odd">' . PHP_EOL; else $res .= '<tr>' . PHP_EOL;
				foreach ($c as $col)
					if ($t[$col] != '-') $res .= '  <td>'.$this->prepareRow($col, $row).'</td>' . PHP_EOL;
				$res .= '</tr>' . PHP_EOL;
			}

			echo $res;
			$this->renderFooter($from, $to);
			echo '</table>' . PHP_EOL;
		}

		function orderLink($col, $title) {
			if ($col && $title) {
				$s = $this->params;
				unset($s[order]);
				unset($s[dir]);
				$a = array();
				foreach ($s as $p => $v) if ($v) $a[] = $p . '=' . $v;
				return '<a href="?' . join('&', $a) . '&order=' . $col . (($this->order == $col) ? (($this->dir == 'desc') ? '' : '&dir=desc'):'') . '">' .
				$title .
				'</a>';
			} else
				return $title;
		}
		function renderHeader() {
			$c = $this->columns();
			$w = $this->colWidths();
			$t = $this->colTitles();

			$i = 0;
			echo '<tr class="header">' . PHP_EOL;
			foreach ($c as $col) {
				if ($t[$col] != '-') {
					$u = array();
					if ($v = $w[$col])           $u[] = 'width: ' . $w[$col] . (is_int($v) ? '%' : '');
					if ($this->order==$col) $u[] = 'text-decoration: underline';
					if (count($u))
						echo '  <td style="' . join(';', $u) . '">' . $this->orderLink($col, $t[$col]) .'</td>' . PHP_EOL;
					else
						echo '  <td>' . $this->orderLink($col, $t[$col]) .'</td>' . PHP_EOL;
				}
			}
			echo '</tr>' . PHP_EOL;
		}

		function paramsToLink($p, $r = null) {
			$result = array();
			foreach (($r ? array_merge($p, $r) : $p) as $param => $value)
				if ($value)
					$result[] = $param . '=' . $value;

			return join('&', $result);
		}

		function renderFooter($from, $to) {
			$a = $this->params;
			$a[pagesize] = $this->maxrows;
			if ($this->order) $a[order] = $this->order;
			if ($this->dir) $a[dir] = $this->dir;
			unset($a[page]);
			$a = $this->paramsToLink($a);

			$c    = $this->columns();
			$first= 1;
			$page = floor($from / $this->maxrows) + 1;
			$last = ceil($this->rows / $this->maxrows);

			$prev = ($prev = $page - 1) < $first ? $first : $prev;
			$next = ($next = $page + 1) > $last ? $last : $next;
			$l    = $this->link . $a;
			$link = array();
			if ($first < $prev) $link[] = '<a href="'.$l.'&page='.$first.'">'.$first.'</a>';
			if ($first < $prev - 1) $link[] = '...';
			if ($prev  < $page) $link[] = '<a href="'.$l.'&page='.$prev.'">'.$prev.'</a>';
			$link[] = '<b>'.$page.'</b>';
			if ($next  > $page) $link[] = '<a href="'.$l.'&page='.$next.'">'.$next.'</a>';
			if ($last  > $next + 1) $link[] = '...';
			if ($last  > $next) $link[] = '<a href="'.$l.'&page='.$last.'">'.$last.'</a>';

			echo '<tr class="header">' . PHP_EOL;
			echo '  <td colspan="' . count($c) . '">Страница: ' . join(' ', $link) . '. Записей на странице: '
			. '<form id="psset" action="' . $l . '&page=1"><select name="pagesize" style="float: none; clear: none;">';
			for ($ps = 1; $ps < 7; $ps++)
				echo '<option value="' . ($ps * 5) . '"' . ($ps * 5 == $this->maxrows ? 'selected' : '') . '>' . ($ps * 5) . '</option>';

			echo '</select>&nbsp;<a class="btn" href="#" onclick="psset.submit()"><span><span style="font-weight: normal">&gt;&gt;</span></span></a></form></td>' . PHP_EOL;
			echo '</tr>' . PHP_EOL;
		}
	}
?>
