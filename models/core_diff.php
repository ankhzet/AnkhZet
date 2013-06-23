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

class DiffBuilder {
	var $DIFF_TEXT_SPLITTERS = array (
		array("/(\x0A[\x0A\s]*\x0A)/", "/\x0A[\x0A\s]*\x0A/")
	, array('/(\x0A)/i', '/\x0A/i')
	, array('/([\.\!\?]+)/', '/[\.\!\?]+/')
	, array('/([\s]+)/', '/[\s]+/')
	);
	var $DIFF_TEXT_SPLITTERS2 = array (
		array("/(\x0A[\x0A\s]*\x0A)/", "/\x0A[\x0A\s]*\x0A/")
	, array('/(\x0A)/i', '/\x0A/i')
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
			$o[] = str_replace(array("\r", PHP_EOL, '"'), array('', '\n', '&quot;'), $repl[0]);
			$n[] = str_replace(array("\r", PHP_EOL, '"'), array('', '\n', '&quot;'), $repl[1]);
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
		$this->f = array_fill(0, $c1 + 1, array_fill(0, $c2 + 1, 0));
		for ($i = $c1 - 1; $i >= 0; $i--)
			for ($j = $c2 - 1; $j >= 0; $j--)
				if ($this->h1[$i] == $this->h2[$j])
					$this->f[$i][$j] = 1 + $this->f[$i + 1][$j + 1];
				else
					$this->f[$i][$j] = max($this->f[$i + 1][$j], $this->f[$i][$j + 1]);

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
				$o = $c[$i][0];
				$j = '';
				while (($i < $l) && ($c[$i][0] == $o)) {
					foreach($c[$i][1] as $hash)
						$j .= $this->hash[$hash];
					$i++;
				}

				switch ($o) {
				case -1: $this->io->left($j);break;
				case  0: $this->io->same($j);break;
				case  1: $this->io->right($j);break;
				}
			}
		}
	}

}