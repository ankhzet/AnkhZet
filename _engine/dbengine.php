<?php

function db_microtime(){
	list($usec, $sec) = explode(' ', microtime());
	return ((double)$usec + (double)$sec);
}

function db_upd_remap(&$value, $collumn) {
	$value = '`' . $collumn . '` = \'' . $value . '\'';
}

class msqlDB {
	private static $connection = null;
	var $host     = '';
	var $login    = '';
	var $password = '';
	var $link     = -1;
	var $db       = '';
	var $debug    = 0;
	static $req   = 0;
	static $ret   = 0;

	private function __construct() {
		require_once 'config.php';
		$c = Config::read('INI', 'cms://config/config.ini');
		$this->debug    = $c->get('db.debug');
		$this->db       = $c->get('db.dbname');
		$this->host     = $c->get('db.host');
		$this->login    = $c->get('db.login');
		$this->password = $c->get('db.password');
		$this->connect();

		if (!$this->open()) {
			debug2($this, '<!-- config');
			throw new Exception('Can\'t open MySQL connection: ' .
			($this->link ? mysql_error($this->link) : 'unresolvable error'));
//			$this->create($this->db);
//			$this->open();
		}
	}

	function __destruct() {
//	  $this->close();
		if ($this->debug) echo 'total [' . self::$req . '] requests for [' . (floor(self::$req * 10000) / 10000) . '] sec<br />' . PHP_EOL;
	}

	public static function o() {
		if (!self::$connection)
			self::$connection = new msqlDB();
		return self::$connection;
	}

	function connect() {
		$this->link = mysql_connect($this->host, $this->login, $this->password);
		if ($this->debug) echo 'connect: ' . $this->link . '<br />' . PHP_EOL;
		mysql_query("SET NAMES 'utf8'");
		return $this->link;
	}
	function reconnect() {
		$this->close();
		$this->connect();
		if (!$this->open()) {
			debug2($this, '<!-- config');
			throw new Exception('Can\'t open MySQL connection: ' .
			($this->link ? mysql_error($this->link) : 'unresolvable error'));
//			$this->create($this->db);
//			$this->open();
		}
	}

	function create($name) {
		return $this->query('CREATE DATABASE `' . $name . '`');
	}

	function query($query) {
		self::$req++;
		if ($this->debug) echo 'queuring('.$this->link.', r#' . self::$req . ') [' . $query . ']<br />' . PHP_EOL;
//		if (USE_TIMELEECH) TimeLeech::addTimes('quering [' . self::$req . ': ' . substr($query, 0, 20) . ']');

		$t = getmicrotime();
		$q = mysql_query($query, $this->link);
		self::$ret += db_microtime() - $t;

//		if (USE_TIMELEECH) TimeLeech::addTimes('queried [' . self::$req . ']');
		if ($this->debug)
			$this->check($q, __FILE__);

		return $q;
	}

	function create_table($name, $collumns, $drop = true) {
		if ($drop)
			$this->query('DROP TABLE IF EXISTS `' . $name . '`');

		return $this->query('CREATE TABLE IF NOT EXISTS `' . $name . '` (' . join(',', $collumns) . ') CHARSET=utf8 engine=MyISAM');
	}

	function insert($table, $values, $getid = false) {
		$q = '';
		if (is_array($values)) {
			$k = array_keys($values);
			if (count($k) && is_string($k[0]))
				$q = $this->insertAssoc($table, $values);
			else {
				$query = '';
				foreach ($values as $value) $query .= ($query != '' ? ', ' : '') . '\'' . $value . '\'';
				$q = 'VALUES (' . $query . ')';
			}
		} else
			$q = 'VALUES (' . $values . ')';

		$r = $this->query('INSERT INTO `' . $table . '` ' . $q);
		if ($getid)
			return $this->query('SELECT LAST_INSERT_ID() as `0`');
		 return $r;
	}
	function insertAssoc($table, $values) {
		$cols = '';
		$vals = '';
		foreach ($values as $key => $value) {
			$cols .= ($cols != '' ? ', ' : '') . '`' . $key . '`';
			$vals .= ($vals != '' ? ', ' : '') . '"' . $value . '"';
		}
		return '(' . $cols . ') VALUES (' . $vals . ')';
	}

	function update($table, $values, $where, $low = false) {
		if (is_array($values)) {
			array_walk($values, 'db_upd_remap');
			$values = join(', ', $values);
		}

		if (is_array($where)) {
			array_walk($where, 'db_upd_remap');
			$where = join(' AND ', $where);
		}

		return $this->query('UPDATE ' . ((!$low) ? '' : 'LOW_PRIORITY') . ' `' . $table . '` SET ' . $values . ($where ? ' WHERE ' . $where : ''));
	}

	function affected() {
		return mysql_affected_rows($this->link);
	}

	function select($table, $filters = '', $cols = '*') {
		if ($filters && (!preg_match('/^[\ ]*((order|group) by|limit).*$/i', $filters)))
			$filters = ' WHERE ' . $filters;
		return $this->query('SELECT ' . $cols . ' FROM ' . $table . $filters);
	}
	function delete($table, $filters = '') {
		if (is_array($filters)) {
			array_walk($filters, 'db_upd_remap');
			$filters = join(' AND ', $filters);
		}
		return $this->query('DELETE FROM ' . $table . ($filters != '' ? ' WHERE ' . $filters : ''));
	}

	function rows($select) {
		return mysql_num_rows($select);
	}

	function fetchrows($select, $fields = null) {
		if (!$select) return null;
		$row = array();
		$rc = mysql_num_rows($select);
		if ($fields) {
			for ($r = 0; $r < $rc; $r++) {
				$col = array();
				foreach ($fields as $field) {
					if (($field != '') & preg_match('/(?i:^[a-z_])/', $field))
						$col[$field] = mysql_result($select, $r, $field);
				}
				$row[$r] = $col;
			}
		} else {
			for ($r = 0; $r < $rc; $r++)
				$row[$r] = mysql_fetch_assoc($select);
		}
		return $row;
	}
	function fetchbyrow($select, $row) {
		if (!$select) return null;
		$rows = Array();
		$rc = mysql_num_rows($select);
		for ($r = 0; $r < $rc; $r++) {
			$r = mysql_fetch_assoc($select);
			$rows[$r[$row]] = $r;
		}
		return $rows;
	}

	function check($select, $pred) {
		if (!$select)
			echo '<span style="color: olive">' . $pred . '</span>: <span style="color: red">' . mysql_error($this->link). '</span><br />' . PHP_EOL;
	}

	function open() {
		if ($this->debug) echo 'open: ' . $this->link . '<br />' . PHP_EOL;
		return mysql_select_db($this->db, $this->link);
	}

	function close() {
		if ($this->debug) echo 'close: ' . $this->link . '<br />'. PHP_EOL;
		$res = mysql_close($this->link);
		$this->link = null;
		return $res;
	}
}

class TblEnum {
	var $data;
	var $rows = 0;
	var $ptr  = 0;

	function __construct($table, $filter = '', $collumns = '*') {
		$db = msqlDB::o();
		$s = $db->select($table, $filter, $collumns);
		$this->data = $db->fetchrows($s);
		unset($db);
		$this->rows = count($this->data);
	}

	function first() {
		$this->ptr = 0;
		return $this->data[$this->ptr];
	}

	function has() {
		return $this->ptr < $this->rows;
	}

	function row() {
		return $this->data[$this->ptr];
	}

	function next() {
		return $this->data[++$this->ptr];
	}

	function __toString() {
		$r = $this->row();
		$o = '';
		foreach ($r as $key => $val)
			$o .= ($o ? ', ' : '') . $key . ': \'' . $val . '\'';
		return '[' . $o . ']';
	}
}

?>