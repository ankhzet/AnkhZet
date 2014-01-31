<?php

function textsplit($text, $splitgroup) {
	$l = preg_split($splitgroup[0], $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	$r = array();
	$c = 0;
	foreach ($l as $line)
		if (!preg_match($splitgroup[1], $line)) {
			if (isset($r[$c]))
				$r[$c] .= $line;
			else
				$r[$c]  = $line;
		} else
			if (isset($r[$c]))
				$r[$c++] .= $line;
			else
				$r[$c++]  = $line;

	return $r;
}

class DiffIO {
	var $show_new = true;
	var $context = 100;
	public function __construct($context) {
		$this->context = $context ? $context : $this->context;
	}

	public function __destruct() {
	}

	public function out($text) {
		echo $text;
	}

	public function left($text) {
		$text = mb_convert_encoding($text, 'UTF8', 'cp1251');
		if ($this->show_new)
			$this->out("<del>$text</del> ");
		else
			$this->out("<ins>$text</ins> ");
	}
	public function right($text) {
		$text = mb_convert_encoding($text, 'UTF8', 'cp1251');
		if ($this->show_new)
			$this->out("<ins>$text</ins> ");
		else
			$this->out("<del>$text</del> ");
	}
	public function same($text) {
		$text = mb_convert_encoding($text, 'UTF8', 'cp1251');
		$l = strlen($text);
		if ($this->context && ($l >= $this->context)) {
			$s1 = safeSubstr($text, $this->context / 2, 100);
			$s2 = safeSubstrl($text, $this->context / 2, 100);
			$text = "<span class=\"context\">{$s1}</span><br />~~~<br /><span class=\"context\">{$s2}</span>";
		}
		$this->out($text);
	}

	public function replace($diff, $old, $new) {
		$old = mb_convert_encoding($old, 'UTF8', 'cp1251');
		$new = mb_convert_encoding($new, 'UTF8', 'cp1251');
		$diff->repl[] = array($old, $new);
		if ($this->show_new)
			if (trim($new))
				$this->out('<ins><span>' . $new . '</span><a class="pin pin' . count($diff->repl) . '"></a></ins>');
			else
				;
		else
			if (trim($old))
				$this->out('<del><span>' . $old . '</span><a class="pin pin' . count($diff->repl) . '"></a></del>');
	}

}
class DiffIOClean extends DiffIO {
	public function replace($diff, $old, $new) {
		$old = mb_convert_encoding($old, 'UTF8', 'cp1251');
		$new = mb_convert_encoding($new, 'UTF8', 'cp1251');
		$diff->repl[] = array($old, $new);
		if ($this->show_new)
			if (trim($new))
				$this->out("<ins>$new</ins>");
			else
				;
		else
			if (trim($old))
				$this->out("<del>$old</del>");
	}

}

class DiffSubsplitter {
	var $stage = 0;

	function __construct() {
		$this->DIFF_TEXT_SPLITTERS = array (
//			array('/(' . PHP_EOL . '[' . PHP_EOL . '\s]*' . PHP_EOL . ')/', '/' . PHP_EOL . '[' . PHP_EOL . '\s]*' . PHP_EOL . '/')
			array('/(' . PHP_EOL . ')/', '/' . PHP_EOL . '/')
		, array('/([\.\!\?]+)/', '/[\.\!\?]+/')
//		, array('/([\>\s]+)/', '/[\>\s]+/')
		);
	}

	function split($t1, $t2, $stage = 0) {
		switch ($stage) {
		case 0:
			$split = $this->sequentialMerge($t1, $t2);
			break;
		default:
			$splitter = isset($this->DIFF_TEXT_SPLITTERS[$stage]) ? $this->DIFF_TEXT_SPLITTERS[$stage] : null;
			$split = $splitter ? array(textsplit($t1, $splitter), textsplit($t2, $splitter)) : false;
		}
//		debug($split);
		return $split;
	}

	function sequentialMerge($t1, $t2) {
//		TimeLeech::addTimes('seq::start()?'.rand());
		$l1 = explode(PHP_EOL, $t1);
		$l2 = explode(PHP_EOL, $t2);
//		TimeLeech::addTimes('seq::explode()?'.rand());
		unset($t1);
		unset($t2);
		$c1 = count($l1);
		$c2 = count($l2);
//		debug(array($c1, $c2));
		$h = array();
		$h1 = array();
		$h2 = array();
		$i = 1;
		$e = 0;
		$u = array();
		// building hash
		foreach ($l1 as $j => $line) {
			if (trim($line) == '' && !$e) {
				$e = $idx = $i++;
				$h[$e] = '';
				continue;
			}

			// array_search() has VERY LOW performance for searching string's (access via array keys is 20 times faster, e.g. 0.05 msec versus 1.0 sec)
			$m = crc32($line); // why hash? hash consumes ~32bit, while text line can be > 4 bytes long (most of the time)
			$idx = isset($u[$m]) ? intval($u[$m]) : 0;
			if (!$idx) {
				$idx = $i++;
				$h[$idx] = $line;
				$u[$m] = $idx;
			}

			$h1[] = $idx;
		}
//		TimeLeech::addTimes('seq::hash1()?'.rand());
		foreach ($l2 as $j => $line) {
			$m = crc32($line);
			$idx = isset($u[$m]) ? intval($u[$m]) : 0;
			if (!$idx) {
				$idx = $i++;
				$h[$idx] = $line;
				$u[$m] = $idx;
			}
			if ($idx != $e) $h2[] = $idx;
		}
		unset($l1);
		unset($l2);
		$c1 = count($h1);
		$c2 = count($h2);
//		TimeLeech::addTimes('seq::hash2()?'.rand());
//		debug2(array($c1, $c2));
		$n1 = array();
		$n2 = array();
		foreach ($h1 as $hash) $n1[$hash] = isset($n1[$hash]) ? intval($n1[$hash]) + 1 : 1;
		foreach ($h2 as $hash) $n2[$hash] = isset($n2[$hash]) ? intval($n2[$hash]) + 1 : 1;
//		debug(array($c1, $c2, $n1, $n2));

		arsort($n1);
		$d = array();
		foreach ($n1 as $key => $count) if ($count <> 1) $d[] = $key;
		foreach ($n2 as $key => $count) if ($count <> 1) $d[] = $key;
		foreach ($d as $key) {
			unset($n1[$key]);
			unset($n2[$key]);
		}

//		debug(array($n1, $n2));

		$n = array_intersect($n1, $n2);
		$n = array_keys($n);
		unset($n1, $n2);
//		TimeLeech::addTimes('seq::uniques()?'.rand());
		$l = array();
		$k = array_flip($n);
//		debug(array($n, $k));
//		return;
		for ($i = 0; $i < count($n); $i++) {
			$hash = $n[$i];
			if (!$hash) continue;
			$i1 = $i3 = array_search($hash, $h1);
			$i2 = $i4 = array_search($hash, $h2);
			$c = 0;
			while (($i1 < $c1) && ($h1[$i1++] == $h2[$i2++]))
				$c++;
			if ($c >= 2) {
				while (($i3 < $c1) && (($t = $h1[$i3++]) == $h2[$i4++]))
					$n[isset($k[$t]) ? $k[$t] : 0] = 0;

				$l[$hash] = $c;
			}
		}
//		TimeLeech::addTimes('seq::lengths()?'.rand());
		arsort($l);
		$s = array();
		$k = array_keys($l);
		while (count($k)) {
			$hash = array_shift($k);
//			if ($l[$hash] < 2) continue;
			$i1 = array_search($hash, $h1);
			$i2 = array_search($hash, $h2);
			if ($i1 === false || $i2 === false)
				continue;

			$s[$hash] = array();
			while (($i1 < $c1) && ($i2 < $c2) && (($t = $h1[$i1]) == $h2[$i2])) {
				$s[$hash][] = $t;
				$i = array_search($t, $k);
				unset($k[$i]);
				$i1++;
				$i2++;
			}
		}
		unset($l);
//		TimeLeech::addTimes('seq::sequences()?'.rand());
//			debug($s);
/*
		$u = array();
		foreach ($s as &$seq)
			$u = array_merge($u, $seq);

		$c = array_count_values($u);
		arsort($c);
		foreach ($c as $hash => $cnt)
			if ($cnt > 1)
				echo "[<b>" . htmlspecialchars(mb_convert_encoding($h[$hash], 'UTF8', 'CP1251')) . "</b>] sequenced for $cnt times...<br />";
/**/
//			debug($s);
/*
		$o1 = array();
		foreach ($h1 as $hash)
			$o1[] = $h[$hash];
		$o2 = array();
		foreach ($h2 as $hash)
			$o2[] = $h[$hash];
/**/

		$u1 = $this->_merge($c1, $h1, $h, $s);
//		TimeLeech::addTimes('seq::merge_h1()?'.rand());
		$u2 = $this->_merge($c2, $h2, $h, $s);
//		TimeLeech::addTimes('seq::merge_h2()?'.rand());

		unset($h, $h1, $h2);
//		debug(array(count($u1), count($u2)));
		return array($u1, $u2);
	}

	function _merge($c, &$_h, &$h, &$s) {
		$u1 = array();
		$i = 0;
		while ($i < $c) {
			if (!($e = $_h[$i++])) continue;
			if ($sequence = &$s[$e]) {
				$t = '';
				$i--;
				foreach ($sequence as $hash) {
					if ($e != $hash) break;
					$_h[$i] = 0;
					$e = (++$i < $c) ? $_h[$i] : null;
					$t .= PHP_EOL . $h[$hash];
				}
				$u1[] = $t;
			} else
				$u1[] = PHP_EOL . $h[$e];
		}
		return $u1;
	}
}

class DiffBuilder {
	var
		$repl, $io, $splitter
	;

	function __construct($io = null, $repl = null) {
		$this->splitter = new DiffSubsplitter();
		$this->repl = $repl ? $repl : array();
		$this->io = $io ? $io : new DiffIO(100);
	}

	function diff($t1, $t2, $stage = 0) {
//		TimeLeech::addTimes('diff::start()?'.rand());
/** /
		if ($t1 == $t2)
			$f = 101;
		else {
			$tt1 = preg_replace('/\W/', '', $t1);
			$tt2 = preg_replace('/\W/', '', $t2);
			if (strlen($tt1) + strlen($tt2) < 40000)
				$s = similar_text($tt1, $tt2, &$f);
			else
				$f = 100;
		}

//		TimeLeech::addTimes('diff::same_text()?'.rand());
		if ($f < 50) {
			$this->io->replace($this, $t1, $t2);
			return $this->getReplaces();
		}/**/
//		debug('split?');
		if ($t1 != $t2) {
			$split = $this->splitter->split($t1, $t2, $stage);
//		TimeLeech::addTimes('diff::split()?'.rand());
			if (is_array($split)) {
				$this->l1 = $split[0];
				$this->l2 = $split[1];
				$this->c1 = count($this->l1);
				$this->c2 = count($this->l2);

				$this->buildHash();
				$this->lcs();
				$this->sequence();
				$this->merge($stage);
			} else
				$this->io->replace($this, $t1, $t2);
		} else
			$this->io->same($t1);

		return $this->getReplaces();
	}

	function getReplaces() {
		$o = array();
		$n = array();
		foreach ($this->repl as $repl) {
			$o[] = str_replace(array(PHP_EOL, '"'), array('\n', '&quot;'), $repl[0]);
			$n[] = str_replace(array(PHP_EOL, '"'), array('\n', '&quot;'), $repl[1]);
		}
		return array($o, $n);
	}

	function buildHash() {
		$this->hash = array();
		$this->h1 = array();
		$this->h2 = array();

		$i = 1;
		$u = array();
		foreach ($this->l1 as $entity) {
			$m = crc32($entity); // why hash? hash consumes ~32bit, while text line can be > 4 bytes long (most of the time)
			$idx = isset($u[$m]) ? intval($u[$m]) : 0;
			if (!$idx) {
				$this->hash[$idx = $i++] = $entity;
				$u[$m] = $idx;
			}
			$this->h1[] = $idx;
		}
		foreach ($this->l2 as $entity) {
			$m = crc32($entity); // why hash? hash consumes ~32bit, while text line can be > 4 bytes long (most of the time)
			$idx = isset($u[$m]) ? intval($u[$m]) : 0;
			if (!$idx) {
				$this->hash[$idx = $i++] = $entity;
				$u[$m] = $idx;
			}
			$this->h2[] = $idx;
		}

		$this->c1 = count($this->h1);
		$this->c2 = count($this->h2);
	}

	function lcs() {
		$f = array();
		$c1 = $this->c1;
		$c2 = $this->c2;

		$sparse = class_exists("SplFixedArray");
		$f = $sparse ? new SplFixedArray($c1 + 1) : array_fill(0, $c1 + 1, 0);
		for ($i = $c1 - 1; $i >= 0; $i--) {
			$f[$i] = $sparse ? new SplFixedArray($c2 + 1) : array_fill(0, $c2 + 1, 0);
			for ($j = $c2 - 1; $j >= 0; $j--)
				if ($this->h1[$i] == $this->h2[$j])
					$f[$i][$j] = 1 + $f[$i + 1][$j + 1];
				else
					$f[$i][$j] = max($f[$i + 1][$j], $f[$i][$j + 1]);
		}
		$this->f = $f;

		return $this->f[0][0];
	}

	function sequence() {
		$this->fts = array();
		$i = $j = 0;
		while ($i < $this->c1 && $j < $this->c2) {
			if ($this->h1[$i] == $this->h2[$j]) {
				$this->fts[] = $this->h1[$i];
				$i++;
				$j++;
			} else
				if ($this->f[$i + 1][$j] >= $this->f[$i][$j + 1])
					$i++;
				else
					$j++;
		}
	}

	function merge($stage) {
		$i = $j = $idx = 0;
		$c1 = $this->c1;
		$c2 = $this->c2;
		$out = array();
		$c = isset($this->fts[$idx]) ? $this->fts[$idx] : 0;
		$cc = count($this->fts);
		while (($i < $c1) && (($t = $this->h1[$i]) != $c)) {
			$out[] = array(-1, $t);
			$i++;
		}

		while ($i < $c1 && $j < $c2) {
			if (!isset($this->fts[$idx])) break;
			$c = $this->fts[$idx];
			while (($j < $c2) && (($t = $this->h2[$j]) != $c)) {
				$out[] = array(1, $t);
				$j++;
			}
			while (($i < $c1 && $j < $c2) && ($t = $this->h1[$i]) == $this->h2[$j]) {
				$out[] = array(0, $t);
				$i++;
				$j++;
				$idx++;
			}

			if ($idx < $cc) {
				$c = $this->fts[$idx];
				while (($i < $c1) && (($t = $this->h1[$i]) != $c)) {
					$out[] = array(-1, $t);
					$i++;
				}
			}
		}
		while ($j < $c2) {
			$out[] = array(1, $this->h2[$j]);
			$j++;
		}
		$o = 0;
		$b = array();
		$c = array();
		foreach ($out as &$block)
			if ($block[0] != $o) {
				if (count($b)) $c[] = array($o, $b);
				$o = $block[0];
				$b = array($block[1]);
			} else
				$b[] = $block[1];

		if (count($b)) $c[] = array($o, $b);

		$this->fts = null;
		$this->f = null;
		$this->h1 = null;
		$this->h2 = null;
		$this->l1 = null;
		$this->l2 = null;
		$b = null;
		$out = null;

		$i = 0;
		$l = count($c);
		while ($i < $l) {
			if ($c[$i][0] < 0 && $c[$i + 1][0] > 0) {
				$t1 = '';
				foreach ($c[$i][1] as $hash)
					$t1 .= $this->hash[$hash];

				$t2 = '';
				foreach ($c[$i + 1][1] as $hash)
					$t2 .= $this->hash[$hash];
				$i += 2;

				if ($t1 != $t2) {
					$db = new DiffBuilder($this->io, $this->repl);
					$db->diff($t1, $t2, $stage + 1);
					$this->repl = $db->repl;
					unset($db);
				} else
					$this->io->same($t1);
			} else {
				$o = $c[$i][0];
				$j = '';
				while (($i < $l) && ($c[$i][0] == $o))
					foreach($c[$i++][1] as $hash)
						$j .= $this->hash[$hash];

				switch ($o) {
				case -1: $this->io->left($j);break;
				case  0: $this->io->same($j);break;
				case  1: $this->io->right($j);break;
				}
			}
		}
	}

}