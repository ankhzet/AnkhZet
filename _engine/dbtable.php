<?php
	require_once "dbengine.php";

	class msqlTableRow {
		const COL_ID = 'id';

		var $table;
		private $data;

		function msqlTableRow($tbl) {
			if ($tbl) $this->table = $tbl;
		}

		function selectBy($columns) {
			$flt = '';
			if (is_array($columns))
				foreach($columns as $column)
					$flt .= ($flt?' AND ':'').$column.' = \''.$this->data[$column].'\'';
			else $flt = $columns . ' = \'' . $this->data[$columns] . '\'';
			$db = msqlDB::o();
			$s  = $db->select($this->table, $flt);
			$res= $db->fetchrows($s);
			if (!$res)
				$this->data = array();
			else
				$this->data = $res[0];
//      $db->close();
			unset($db);
		}
		function deleteBy($columns) {
			$flt = "";
			foreach($columns as $column)
				$flt .= ($flt?' AND ':'').$column.' = \''.$this->data[$column].'\'';
			$db = msqlDB::o();
			$s  = $db->delete($this->table, $flt);
//      $db->close();
			unset($db);
		}

		function valid() {
		 return $this->_get(self::COL_ID) !== null;
		}

		function insert() {
			if ($this->_get(self::COL_ID) !== null)
				$this->deleteBy(self::COL_ID);

			$db = msqlDB::o();
			$db->insert($this->table, $this->data);
//      $db->close();
			unset($db);
		}

		function _get($col) {
			return $this->data[$col];
		}
		function _set($col, $value, $update = false) {
			$this->data[$col] = $value;
			if ($update) $this->selectBy($col);
		}

	}

?>