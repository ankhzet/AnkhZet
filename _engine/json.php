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
			$data = '"' . addslashes(str_replace(array("'", '"'), array('&#39;', '&quot;'), $data)) . '"';
		}
		return $name ? '"'.$name.'": '.$data : $data;
	}

	function format2 ($tab, $data, $name = null) {
		$delim = '=';
		switch (true) {
		case is_bool($data):
		case is_numeric($data):
			break;
		case is_array($data):
			$delim = '';
			$e = array();
			$assoc = is_assoc($data);
			if (!$assoc)
				foreach ($data as $idx => $row)
					$e[] = format2($tab, $row, "item_$idx");
			else
				foreach ($data as $id => $row)
					$e[] = format2($tab, $row, $id);

			$data = (!!$data && $assoc)
				? "{\n$tab\t" . join(";\n$tab\t", $e) . ";\n$tab\t}"
				: "{\n$tab" . join(";\n$tab", $e) . ";\n$tab}";
			break;
		default:
			$data = '"' . addslashes(str_replace("'", '&#39;', $data)) . '"';
		}
		return $name ? "{$tab}$name {$delim} $data" : $data;
	}

	function JSON_result($result, $data = null, $die = true) {
		$response = '{"result": "' . addslashes($result) . '"' . (($data || is_array($data)) ? ', ' . format($data, 'data') : '') . '}';
		if ($die)
			die($response);
		else
			return $response;
	}

	function Config_result($name, $result, $data = null, $die = true) {
		$response = "config \"$name\" {\n\tresult = \"" . addslashes($result) . '"' . (($data || is_array($data)) ? ";\n" . format2("\t", $data, 'data') : '') . ";\n}";
		if ($die)
			die($response);
		else
			return $response;
	}
