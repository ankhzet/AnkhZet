<?php
	define('JSON_Ok', 'ok');
	define('JSON_Fail', 'err');

	function is_assoc(&$array) {
		foreach ($array as $key => &$val)
			if (!is_numeric($key))
				return true;

		return false;
	}

	function format ($data, $name = null) {
		switch (true) {
		case is_bool($data):
		case is_numeric($data):
			break;
		case is_array($data):
			$e = array();
			$assoc = is_assoc($data);
			if (!$assoc)
				foreach ($data as $row)
					$e[] = format($row);
			else
				foreach ($data as $id => $row)
					$e[] = format($row, $id);

			$data = (!!$data && $assoc)
				? '{'.PHP_EOL . join(PHP_EOL.',', $e) . PHP_EOL.'}'
				: '['.PHP_EOL . join(PHP_EOL.',', $e) . PHP_EOL.']';
			break;
		default:
			$data = '"' . addslashes(str_replace("'", '&#39;', $data)) . '"';
		}
		return $name ? '"'.$name.'": '.$data : $data;
	}

	function JSON_result($result, $data = null, $die = true) {
		$response = '{"result": "' . addslashes($result) . '"' . (($data || is_array($data)) ? ', ' . format($data, 'data') : '') . '}';
		if ($die)
			die($response);
		else
			return $response;
	}
?>