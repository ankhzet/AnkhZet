<?php

	class CSpline {
		var $splines = array(); // x => 0, a => 1, b => 2, c => 3, d => 4
		var $pts = 0;

		function build($x, $y) {
			$this->pts = ($n = count($x));
			foreach ($x as $i => $val_x)
				$this->splines[] = array(0 => $val_x, 1 => $y[$i], 2 => 0.0, 3 => 0.0, 4 => 0.0);

			$alpha = array();
			$beta = array();
			$alpha[0] = 0.0;
			$beta[0] = 0.0;
			$A = $B = $C = $F = $hi = $hi1 = $z = 0.0;
			for ($i = 1; $i < $n - 1; $i++) {
				$hi = $x[$i] - $x[$i - 1];
				$hi1 = $x[$i + 1] - $x[$i];
				if ($hi == 0) $hi = 0.001;
				if ($hi1 == 0) $hi1 = 0.001;
				$A = $hi;
				$C = 2 * ($hi + $hi1);
				$B = $hi1;
				$F = 6 * (($y[$i + 1] - $y[$i]) / $hi1 - ($y[$i] - $y[$i - 1]) / $hi);
				$z = $A * $alpha[$i - 1] + $C;
				$alpha[$i] = - $B / $z;
				$beta[$i] = ($F - $A * $beta[$i - 1]) / $z;
			}
//			$this->splines[$n - 1][3] = ($F - $A * $beta[$n - 2]) / ($C + $A * $alpha[$n - 2]);

			for ($i = $n - 2; $i > 0; --$i)
				$this->splines[$i][3] = $alpha[$i] * $this->splines[$i + 1][3] + $beta[$i];

			$alpha = null;
			$beta = null;

			for ($i = $n - 1; $i > 0; --$i) {
				$hi = $x[$i] - $x[$i - 1];
				if ($hi == 0) $hi = 0.001;
				$this->splines[$i][4] = ($this->splines[$i][3] - $this->splines[$i - 1][3]) / $hi;
				$this->splines[$i][2] = $hi * (2 * $this->splines[$i][3] + $this->splines[$i - 1][3]) / 6 + ($y[$i] - $y[$i - 1]) / $hi;
			}
		}

		function f($x) {
			if (!$this->splines) return 0.0;

			$s = 0;
			$n1 = count($this->splines) - 1;
			if ($x <= $this->splines[0][0])
				$s = 1;
			elseif ($x >= $this->splines[$n1][0])
				$s = $n1;
			else {
				$i = 0;
				$j = $n1;
				while ($i + 1 < $j) {
					$k = intval($i + ($j - $i) / 2);
					$t = $this->splines[$k][0];
					if ($x <= $t)
						$j = $k;
					else
						$i = $k;
				}
				$s = $j;
			}
			$s = &$this->splines[$s];
			$dx = $x - $s[0];
			return
				$s[1] + ($s[2] + ($s[3] / 2 + $s[4] * $dx / 6) * $dx) * $dx;
		}

		function clear() {
			$this->splines = array();
			$this->pts = 0;
		}
	}


