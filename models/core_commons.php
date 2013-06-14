<?php

	function makeArgs($args) {
		$r = array();
		foreach ($args as $key => $arg)
			if (is_array($arg))
				foreach ($arg as $p => $val)
					if ($val)
						$r[] = $key . '[' . $p . ']=' . $val;
					else;
			else
				if ($arg)
					$r[] = $key . '=' . $arg;
		return join('&', $r);
	}

	function filterChecks($field, $collumn, &$f) {
		$c = $_REQUEST[$field];

		$_c  = $c ? array_keys($c) : array();
		if ($_c) $f[] = count($_c) > 1 ? $collumn . ' in (' . join(', ', $_c) . ')' : $collumn . ' = ' . $_c[0];
	}

	function filterRange($field, $collumn, &$f) {
		$c = $_REQUEST[$field];
		if ($c) {
			$r = array();
			if ((float)$c[0] > 0)
				$r[] = $collumn . ' >= ' . (float)$c[0];
			if ((float)$c[1] > 0)
				if ((float)$c[1] < (float)$c[0])
					return;
				else
					$r[] = $collumn . ' <= ' . (float)$c[1];
			if (count($r))
				$f[] = '(' . join(' AND ', $r) . ')';
		}
	}

	function filterList($field, $col, &$f) {
		$a = array();
		if (is_array($_REQUEST[$field]))
			foreach ($_REQUEST[$field] as $n)
				if($n = intval($n))
					$a[] = $n;
				else;
		else
			if ($n = intval($_REQUEST[$field]))
				$a[] = $n;

		if (count($a))
			$f[] = $col . (count($a) > 1 ? ' in (' . join(',', $a) . ')' : ' = ' . $a[0]);
	}

	function filterMemo($field, $collumn, &$f) {
		$c = addslashes($_REQUEST[$field]);
		if ($c)
			$f[] = '(' . $collumn . ' LIKE \'%' . $c . '%\')';

	}

?>