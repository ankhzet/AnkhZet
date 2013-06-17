<?php

function textsplit($text, $splitgroup) {
	$l = preg_split($splitgroup[0], $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	$r = array();
	$c = 0;
	foreach ($l as $line)
		if (!preg_match($splitgroup[1], $line)) {
			$r[$c] .= $line;
		} else
			$r[$c++] .= $line;
	return $r;
}

class DiffIO {
	public function left($text) {
		echo '<del class="old deleted">' . rtrim($text) . '</del> ';
	}
	public function right($text) {
		echo '<ins class="new inserted">' . rtrim($text) . '</ins> ';
	}
	public function same($text) {
		echo $text;
	}

	public function replace($diff, $old, $new) {
		$diff->repl[] = array($old, $new);
		if (trim($new))
			echo '<span class="new"><span>' . $new . '</span><a class="pin" href="#" onclick="h_edit(this, ' . count($diff->repl) . ');return false;"></a></span>';
	}

}


class DiffBuilder0 {
	var $DIFF_TEXT_SPLITTERS = array (
		array('/(\x0A[\x0A\s]*\x0A)/sm', '/\x0A[\x0A\s]*\x0A/sm')
	, array('/(\x0A)/', '/\x0A/')
	, array('/([\.\!\?]+)/', '/[\.\!\?]+/')
	, array('/([\s]+)/', '/[\s]+/')
	);

	var
		$l1, $l2, $hash
	, $h1, $h2, $diff
	, $diff1, $diff2
	, $c1, $c2, $old, $new
	, $fits, $f, $fts
	, $repl, $io
	;

	function __construct($io = null, $repl = null) {
		$this->repl = $repl ? $repl : array();
		$this->io = $io ? $io : new DiffIO();
	}

	function fit($s, $d) {

		$r = $this->f[$s][$d];
		if (($r >= 0)) return $r;

		// has src lines ?
		$k = ($s + 1 < $this->c1) ? $this->fit($s + 1, $d) : 0;

		if ($this->fits[$s]) {
			// has same string in dest, try to fit with it
			$si = 0;
			do {
				$i = $this->fts[$s][$si++];
			} while (($i >= 0) && ($i < $d));
			if ($i < $d) {
				// oops, already can't be fitted
				$this->f[$s][$d] = $k;
				return $k;
			}

			$_ = 0;
			$u = $s;
			while (($u < $this->c1) && ($i < $this->c2) && ($this->h1[$u] == $this->h2[$i])) {
				$_++;
				$u++;
				$i++;
			}

			if ($i < $this->c2) // has dst lines
				$maxfit = $this->fit($u, $i) + $_; // fit next line on next sourceline
			else
				$maxfit = $_;

			if ($maxfit > $k) { // with this line fitted maxfitness is larger ?
				while ($u-- > $s) {
					$i--;
					$this->diff[$u] = $i;
				}
				$this->f[$s][$d] = $maxfit;
				return $maxfit;
			}

		}
		$this->f[$s][$d] = $k;
		return $k;
	}

	function shiftDiffs() {
		$e1 = 0;
		$e2 = 0;
		$i1 = 0;
		$i2 = 0;
		while ($i1 < $this->c1) {
			$d = $this->diff[$i1];
			while ($d > $i2) {
				$this->diff1[$e1++] = array(-1, '');
				$this->diff2[$e2++] = array($i2, $this->l2[$i2]);
				$i2++;
			}
			$this->diff1[$e1++] = array($i1, $this->l1[$i1]);
			if ($d < $i2)
				$this->diff2[$e2++] = array(-1, '');
			else {
				$this->diff2[$e2++] = array($i2, $this->l2[$i2]);
				$i2++;
			}
			$i1++;
		}
		while ($i2 < $this->c2) {
			$this->diff1[$e1++] = array(-1, '');
			$this->diff2[$e2++] = array($i2, $this->l2[$i2]);
			$i2++;
		}
	}

	function buildHash() {
		$c = 0;
		$empty = 0;
		for ($i = 0; $i < $this->c1; $i++) {
			$s = trim($this->l1[$i]);
			if ($s != '')
				for ($j = 0; $j < $c; $j++)
					if ($this->hash[$j] == $s) {
						$this->h1[$i] = $j;
						continue 2;
					}
			else
				if (!$empty) $empty = $c;

			$this->hash[$c] = $s;
			$this->h1[$i] = $c;
			$c++;
		}
		for ($i = 0; $i < $this->c2; $i++) {
			$s = trim($this->l2[$i]);
			if ($s != '')
				for ($j = 0; $j < $c; $j++)
					if ($this->hash[$j] == $s) {
						$this->h2[$i] = $j;
						continue 2;
					}
			else {
				$this->h2[$i] = $empty;
				continue;
			}

			$this->hash[$c] = $s;
			$this->h2[$i] = $c;
			$c++;
		}

		for ($i = 0; $i < $this->c1; $i++) {
			$this->diff[$i] = -1;
			$this->fits[$i] = false;
			$e = 0;
			for ($j = 0; $j < $this->c2; $j++)
				if ($this->h1[$i] == $this->h2[$j]) {
					$this->fits[$i] = true;
					$this->fts[$i][$e] = $j;
					$e++;
				}
			$this->fts[$i][$e] = -1;
		}

		for ($i = 0; $i < $this->c2; $i++)
			$this->f[$i] = array_fill(0, $this->c2, -1);

	}

	function output($splitters, $text) {
		if ($this->old && $this->new)
			if (count($splitters)) {
				if (trim($this->old) != trim($this->new)) {
					$db = new DiffBuilder($this->io, $this->repl);
					$db->diff($this->old, $this->new, $splitters);
					$this->repl = $db->repl;
				} else
					$this->io->outNew($this->new);
			} else
				$this->io->outReplace($this, $this->old, $this->new);
		else {
			if ($this->old != '') $this->io->outOld($this->old);
			if ($this->new != '') $this->io->outNew($this->new);
		}
		$this->old = '';
		$this->new = '';
		$this->io->outSame($text);
	}

	function diff($t1, $t2, $splitgroups) {

		$splitter = array_shift($splitgroups);
		$this->l1 = textsplit($t1, $splitter);
		$this->l2 = textsplit($t2, $splitter);
		$this->c1 = count($this->l1);
		$this->c2 = count($this->l2);
//		debug($this);
		if (($this->c1 > 5000) || ($this->c2 > 5000)) {
			return array(array('Too many data', $t1), array('Too many data', $t2));
		}
		$this->diff = array();
		$this->diff1 = array();
		$this->diff2 = array();
		$this->hash = array();
		$this->h1 = array();
		$this->h2 = array();
		$this->fits = array();
		$this->f = array();
		$this->fts = array();

		if ($t1 == $t2)
			while ($this->c1-- > 0)
				$this->diff[$this->c1] = $this->c1;
		else {
			$i = $this->c1;
			while ($i-- > 0)
				$this->diff[$i] = -1;

			$this->buildHash();
			if ($this->c1 > 0)
				$this->fit(0, 0);
		}
		$this->shiftDiffs();
		$data = array();
		foreach ($this->diff1 as $i => $a) {
			$b = $this->diff2[$i];
			$data[] = array(0 => $a[0], 1 => $b[0], 2 => array($a[1], $b[1]));
		}

		$this->old = '';
		$this->new = '';
		$o = 0;
		foreach ($data as $i => $block) {
			if (($block[0] * $block[1]) > 0) {
				$this->output($splitgroups, $block[2][1]);
			} else {
				if ($block[1] < 0) {
					if ($this->new != '') $this->output($splitgroups, '');
					$this->old .= $block[2][0];
					continue;
				}
				if ($block[0] < 0) {
					$this->new .= $block[2][1];
					continue;
				}
				if ($block[2][0] != $block[2][1]) {
					$this->output($splitgroups, '');
					$this->old = $block[2][0];
					$this->new = $block[2][1];
				} else
					$this->output($splitgroups, $block[2][1]);
			}
		}
		$this->output($splitgroups, '');

		$o = array();
		$n = array();
		foreach ($this->repl as $repl) {
			$o[] = str_replace(PHP_EOL, '<br />', htmlspecialchars($repl[0]));
			$n[] = str_replace(PHP_EOL, '<br />', htmlspecialchars($repl[1]));
		}

		return array($o, $n);
	}
}

/* */


class DiffBuilder {
	var $DIFF_TEXT_SPLITTERS = array (
		array("/(\x0A[\x0A\s]*\x0A)/", "/\x0A[\x0A\s]*\x0A/")
	, array('/(\x0A)/i', '/\x0A/i')
	, array('/([\.\!\?]+)/', '/[\.\!\?]+/')
	, array('/([\s]+)/', '/[\s]+/')
	);

	var
		$repl, $io
	;

	function diff($t1, $t2, $splitgroups) {
		$splitter = array_shift($splitgroups);
		if (($splitter) && ($t1 != $t2)) {
			$this->l1 = textsplit($t1, $splitter);
			$this->l2 = textsplit($t2, $splitter);
			$this->c1 = count($this->l1);
			$this->c2 = count($this->l2);

			$this->buildHash();
			$this->lcs();
			$this->sequence();
			$this->merge($splitgroups);
		} else
			$this->io->replace($this, $t1, $t2);

		$o = array();
		$n = array();
		foreach ($this->repl as $repl) {
			$o[] = str_replace(array(PHP_EOL, '"'), array('<br/>', '&quot;'), $repl[0]);
			$n[] = str_replace(array(PHP_EOL, '"'), array('<br/>', '&quot;'), $repl[1]);
		}
		return array($o, $n);
	}

	function __construct($io = null, $repl = null) {
		$this->repl = $repl ? $repl : array();
		$this->io = $io ? $io : new DiffIO();
	}

	function buildHash() {
		$this->hash = array();
		$this->h1 = array();
		$this->h2 = array();

		$idx = 0;
		foreach ($this->l1 as $_ => $line) {
			$i = array_search($line, $this->hash);
			if ($i === false)
				$this->hash[$idx++] = $line;
			$this->h1[$_] = ($i !== false) ? $i : $idx - 1;
		}
		foreach ($this->l2 as $_ => $line) {
			$i = array_search($line, $this->hash);
			if ($i === false)
				$this->hash[$idx++] = $line;
			$this->h2[$_] = ($i !== false) ? $i : $idx - 1;
		}

		$this->c1 = count($this->h1);
		$this->c2 = count($this->h2);
	}

	function lcs() {
		$this->f = array();
		$c1 = $this->c1;
		$c2 = $this->c2;
		for ($i = $c1; $i >= 0; $i--)
			for ($j = $this->c2; $j >= 0; $j--) {
				if ($i == $this->c1 || $j == $this->c2)
					$this->f[$i][$j] = 0;
				else
					if ($this->h1[$i] == $this->h2[$j])
						$this->f[$i][$j] = 1 + $this->f[$i + 1][$j + 1];
					else
						$this->f[$i][$j] = max($this->f[$i + 1][$j], $this->f[$i][$j + 1]);
			}
		return $this->f[0][0];
	}

	function sequence() {
		$this->fts = array();
		$i = $j = 0;
		while ($i < $this->c1 && $j < $this->c2) {
			if ($this->h1[$i] == $this->h2[$j]) {
				array_push($this->fts, $this->h1[$i]);
				$i++;
				$j++;
			} else
				if ($this->f[$i + 1][$j] >= $this->f[$i][$j + 1])
					$i++;
				else
					$j++;
		}
	}

	function merge($splitgroups) {
		$i = $j = $idx = 0;
		$c1 = $this->c1;
		$c2 = $this->c2;
		$this->out = array();
		$c = $this->fts[$idx];
		while (($i < $c1) && (($t = $this->h1[$i]) !== $c)) {
			$this->out[] = array(-1, $t);
			$i++;
		}
//		debug(array($this->h1, $i), '>>>');
		while ($i < $c1 && $j < $c2) {
			$c = $this->fts[$idx];
			while (($j < $c2) && (($t = $this->h2[$j]) !== $c)) {
				$this->out[] = array( 1, $t);
				$j++;
			}
			while (($i < $c1) && ($t = $this->h1[$i]) == $this->h2[$j]) {
				$this->out[] = array(0, $t);
				$i++;
				$j++;
				$idx++;
			}
			$c = $this->fts[$idx];
			while (($i < $c1) && (($t = $this->h1[$i]) !== $c)) {
				$this->out[] = array(-1, $t);
				$i++;
			}
		}
		while (($j < $c2) && (($t = $this->h2[$j]) !== $c)) {
			$this->out[] = array( 1, $t);
			$j++;
		}
		$o = 0;
		$b = array();
		$c = array();
		foreach ($this->out as $block)
			if ($block[0] != $o) {
				if (count($b))
					$c[] = array($o, $b);
				$o = $block[0];
				$b = array($block[1]);
			} else
				$b[] = $block[1];
				if (count($b))
					$c[] = array($o, $b);

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

//				debug(array(($t1), ($t2)), 'T1 => T2');
				$db = new DiffBuilder($this->io, $this->repl);
				$db->diff($t1, $t2, $splitgroups);
				$this->repl = $db->repl;
				unset($db);
				$db = null;
			} else {
				switch ($c[$i][0]) {
				case -1: foreach($c[$i][1] as $hash) $this->io->left($this->hash[$hash]);break;
				case  0: foreach($c[$i][1] as $hash) $this->io->same($this->hash[$hash]);break;
				case  1: foreach($c[$i][1] as $hash) $this->io->right($this->hash[$hash]);break;
				}
				$i++;
			}
		}
	}

}