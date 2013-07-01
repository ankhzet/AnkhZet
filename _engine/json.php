<?php
	define(JSON_Ok, 'ok');
	define(JSON_Fail, 'err');


	function format ($data, $name = null) {
		switch (true) {
		case is_bool($data):
		case is_numeric($data):
			break;
		case is_array($data):
			$e = array();
			$k = array_keys($data);
			arsort($k);
			if (is_numeric($k[0]))
				foreach ($data as $row)
					$e[] = format($row);
			else
				foreach ($data as $id => $row)
					$e[] = format($row, $id);
			$data = is_numeric($k[0])
				? '['.PHP_EOL . join(PHP_EOL.',', $e) . PHP_EOL.']'
				: '{'.PHP_EOL . join(PHP_EOL.',', $e) . PHP_EOL.'}';
			break;
		default:
			$data = '"' . addslashes(str_replace("'", '&#39;', $data)) . '"';
		}
		return $name
			? '"' . $name . '": ' . $data
			: $data;
	}

	function JSON_result($result, $data = null) {
		die('{"result": "' . addslashes($result) . '"' . (($data || is_array($data)) ? ', ' . format($data, 'data') : '') . '}');
	}
?>