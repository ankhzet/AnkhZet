<?php
	require_once '../base.php';


	function bin2str($data) {
		return strrev(base_convert($data, 10, 2));
	}

	class QRCode {
		var $BIG = array(
			0 => array(
				0 => array(
					0xFE, 0x82, 0xBA, 0xBA
				, 0xBA, 0x82, 0xFE, 0x00
				)
			, 1 => array(
					0x00, 0xFE, 0x82, 0xBA
				, 0xBA, 0xBA, 0x82, 0xFE
				)
			)
		, 1 => array(
				0 => array(
					0x7F, 0x41, 0x5D, 0x5D
				, 0x5D, 0x41, 0x7F, 0x00
				)
			, 1 => array(
					0xF8, 0x88, 0xA8, 0x88
				, 0xF8, 0x00, 0x00, 0x00
				)
			)
		);

		var $bytes = array();
		var $d = 1;

		static function get() {
			return new self();
		}

		function putByte($x, $y, $byte) {
			$this->bytes[$y][$x] = $byte;
		}
		function putAnchor($x, $y, $a_data) {
			foreach ($a_data as $row_id => $byte)
				$this->putByte($x, $y + $row_id, $byte);
		}

		static function enc4bit($bits, $parity) {
			$i1 = ($bits & 0x1) >> 0;
			$i2 = ($bits & 0x2) >> 1;
			$i3 = ($bits & 0x4) >> 2;
			$i4 = ($bits & 0x8) >> 3;
			//  3 5 6 7    1 2 3 4 5 6 7
			//  0 1 0 0 => 1 0 0 1 1 0 0
			//1=0 + 1 + 0=1
			//2=0 + 0 + 0=0
			//3=1 + 0 + 0=1
			$r1 = ($i1 + $i2 + $i4) % 2;
			$r2 = ($i1 + $i3 + $i4) % 2;
			$r3 = ($i2 + $i3 + $i4) % 2;
			$byte = 0;

			foreach (array_reverse(array($r1, $r2, $i1, $r3, $i2, $i3, $i4, intval(!!$parity))) as $idx => $bit)
				$byte ^= $bit << $idx;

			return $byte;
		}

		static function checkHash4Bit($bits) {
			$k1 = ($bits & 0x01) >> 0;
			$k2 = ($bits & 0x02) >> 1;
			$k3 = ($bits & 0x04) >> 2;
			$k4 = ($bits & 0x08) >> 3;
			$k5 = ($bits & 0x10) >> 4;
			$k6 = ($bits & 0x20) >> 5;
			$k7 = ($bits & 0x40) >> 6;
			$k8 = ($bits & 0x80) >> 7;

			$e0 = ($k1 + $k3 + $k5 + $k7) % 2;
			$e1 = ($k2 + $k3 + $k6 + $k7) % 2;
			$e2 = ($k4 + $k5 + $k6 + $k7) % 2;
//			debug2(join('', array($e0, $e1, $e2)), join('', array($k1, $k2, $k3, $k4, $k5, $k6, $k7, $k8)));
			return array($e0 | ($e1 << 1) | ($e2 << 2), $k3, $k5, $k6, $k7);
		}

		static function encode($data) {
			$r = array();
			foreach ($data as $byte) {
				$lo = ($byte & 0x0F);
				$hi = ($byte & 0xF0) >> 4;
				$r[] = $r1 = self::enc4bit($lo, $lo % 2);
				$r[] = $r2 = self::enc4bit($hi, $hi % 2);
//				debug2(array(bin2str($r1), bin2str($r2)), $byte . '>' . bin2str($byte));
			}
			return $r;
		}

		static function dec4bit($byte) {
			$e = self::checkHash4Bit($byte);
//			debug2($e, 'check hash: ' . bin2str($byte));
			if ($e[0]) { // byte is damaged
				$byte ^= (0x1 << $e[0]); // toggled bit ap $p pos as (byte xor position_mask)
				$e = self::checkHash4Bit($byte);
				if ($e[0]) // still damaged
					return false;
			}
			return $e[1] | ($e[2] << 1) | ($e[3] << 2) | ($e[4] << 3);
		}
		static function decode($data) {
			$r = array();
			$c = count($data);
			while ($c > 0) {
				$b1 = array_shift($data);
				$b2 = array_shift($data);
				$c -= 2;
				$b3 = self::dec4bit($b1);
				$b4 = self::dec4bit($b2);
//				debug2(array($c, $b1, $b2, $b3, $b4, $b3 | ($b4 << 4)), 'pair, lo::hi =&gt; dec lo, dec hi, dec res');
				if ($b3 === false || $b4 === false)
					return count($data) - $c + 2;

				$r[] = $b3 | ($b4 << 4);
			}
			return $r;
		}

		function gen($d, $data) {
			$this->d = $d;
			$x = $d - 1;
			$y = ($d - 1) * 8;
			$this->bytes = array();
			$this->putAnchor( 0, 0, $this->BIG[0][0]);
			$this->putAnchor($x, 0, $this->BIG[1][0]);
			$this->putAnchor( 0,$y, $this->BIG[0][1]);
			$this->putAnchor($x,$y, $this->BIG[1][1]);

			$enc = self::encode($data);
			$dec = self::decode($enc);
//			debug2(array($data, $enc, $dec));
			if (is_array($dec))
				foreach ($data as $idx => $byte)
					if ($byte != $dec[$idx]) {
						debug2(array($idx, $byte, $dec[$idx]));
						return;
					} else;
			else {
//				debug2('decoding failed');
//				return false;
			}

			for ($cy = 0; $cy <= $d * 8 - 1; $cy++)
				for ($cx = 0; $cx <= $d - 1; $cx++) {
					$at_anchor =
							overlaps($cx, $cy, 0, 0)
						||overlaps($cx, $cy,$x, 0)
						||overlaps($cx, $cy, 0,$y)
						||overlaps($cx, $cy,$x,$y);

					if ($at_anchor) continue;

					$byte = array_shift($enc) & 0xFF;
					$this->putByte($cx, $cy, $byte);
				}

//			debug2($this->bytes);
		}

		static function unpack($byte) {
			$r = array();
			$b = $byte;
			for ($i = 0; $i < 8; $i++) {
				$r[8 - $i - 1] = intval(!($b & 0x01));
				$b = $b >> 1;
			}

			return $r;
		}

		static function renderByte($img, $cell, $x, $y, $byte, $sample, $c) {
			$cx = $x * 8 * $cell;
			$cy = $y * $cell;
			foreach (self::unpack($byte) as $bit => $val) {
				$x = $cx + $bit * $cell;
				if (!!$val == $sample)
					imagefilledrectangle ($img, $x, $cy, $x + $cell - 1, $cy + $cell - 1, $c);
			}
		}

		function render($cell_size, $filename = null) {
			$d = $this->d * 8 * $cell_size;
			$img   = ImageCreate($d, $d);
			$black = ImageColorAllocate($img, 254, 254, 254);
			$white = ImageColorAllocate($img, 255, 255, 255);
			$back  = $white;
			$fore = $black;

//			imagecolortransparent($img, $back);
			ImageFill($img, 0, 0, $back);

			foreach ($this->bytes as $row_id => $byte_row)
				foreach ($byte_row as $cell_id => $byte)
					self::renderByte($img, $cell_size, $cell_id, $row_id, $byte, false, $c[0]);


			imagetruecolortopalette($img, true, 2);

			if (!$filename) header('Content-Type: image/png');
			return $filename ? imagepng($img, $filename) : imagepng($img);
		}
	}

	function overlaps($x, $y, $cx, $cy) {
		return
			($x == $cx) && ($y >= $cy && $y < $cy + 8);
	}

/*	if ($id = post_int('data')) {
		$qr = QRCode::get();
		$b1 = ($id >>  0) & 0xFF;
		$b2 = ($id >>  8) & 0xFF;
		$b3 = ($id >> 16) & 0xFF;
		$b4 = ($id >> 24) & 0xFF;
		$bytes = array($b1, $b2, $b3, $b4);
		$data = array($b1);
		$qrsize = 4;
		$stored = ($qrsize * $qrsize - 4) * 8 - 8;
/** /
		$data = array();
		for ($i = 0; $i < $stored / 4; $i++) {
			$chunk = ($i % 5)
			? $bytes
			: array(rand(0, 255), rand(0, 255), rand(0, 255), rand(0, 255));
			$data = array_merge($data, $chunk);
		}
/** /
//		$data = array_fill(0, 23, $id);
//		debug2($data, $stored);
		$qr->gen($qrsize, $data);
		$qr->render(8);
	} else {
		$id1 = rand();
		$id = ($id1 << 16) | rand();
		$b1 = ($id >>  0) & 0xFF;
		$b2 = ($id >>  8) & 0xFF;
		$b3 = ($id >> 16) & 0xFF;
		$b4 = ($id >> 24) & 0xFF;
		$bytes = array($b1, $b2, $b3, $b4);
		$data = array();
		for ($i = 0; $i < (4 - 1) * 4 * 8; $i += 4) {
			$chunk = ($i % 2) ? array(rand(255), rand(255), rand(255), rand(255)) : $bytes;
			$data = array_merge($data, $chunk);
		}

//		debug2($data, $id);
?>
	<body bgcolor="#e9e9e9">
	<?=$id?><br />
	<img src="/models/core_qrcode.php?data=<?=$id?>" />
<?php
	}
	*/