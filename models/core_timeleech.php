<?php
	require_once '../base.php';

	if (User::ACL() < ACL::ACL_MODER) JSON_result(JSON_Fail, 'access denied');

	$file = '/timeleech.ini';

	$data = unserialize(@file_get_contents(ROOT . $file));
	$leech = $data['leech'];
	$c = count($leech);
	$t = (float)$leech[$c - 1]['time'];
	$m = 9999;
	$a = array();
	$o = 9999;
	$e = 0.00001;
	foreach ($leech as $idx => $d)
		if ($idx && ($idx != $c - 1)) {
			$v = (float)$d["delta"];
			if ($v >= $e) {
				if ($v < $m)
					$m = $v;
			}

			$o = $v;
		}

	do {
		$a = array();
	foreach ($leech as $idx => $d)
		if ($idx && ($idx != $c - 1)) {
			$v = (float)$d["delta"];
			if ($v < $e)
				$a[] = $idx;
		}

	$f = $a[0];
	$l = $f;
	$p = $f;
	$d = array();
	foreach ($a as $idx) {
//		echo "$idx => ".$leech[$idx]['delta']."<br>";
		if (abs($p - $idx) > 1) {
//			echo "[$f, $p, $idx]<br>";
			if (abs($p - $f) >= 1) {
				$f++;
				while ($f <= $p) {
					$d[] = $f;
					$f++;
				}
			}
			$f = $idx;
		}
		$p = $idx;
	}
	if (abs($p - $f) >= 1) {
		$f++;
		while ($f <= $p) {
			$d[] = $f;
			$f++;
		}
	}
	$del = count($d);
//	debug2($a);
//	debug2($d);
	$a = $d;

		foreach ($a as $idx)
			unset($leech[$idx]);
		$leech = array_values($leech);
	} while ($del);

	$x = array();
	foreach ($leech as $idx => $d) {
		$v = (float)$d["delta"];
		if ($v >= $e)
			$x[$idx] = $v;
	}


//	debug2($leech);
//	debug2($x);
	$c = count($leech);
//	$x = array_values($x);
	arsort($x);
//	debug2($x);

	define(MAX_RED, 5);
	$k = array_slice(array_keys($x), 0, MAX_RED);
	$u = array_slice($x, 0, MAX_RED);
	$x = array();
	foreach ($k as $i => $idx)
		$x[$idx] = $u[$i];
//	debug2($x);

	$font = 8;
	$d = ($font * 2) * $c;//$t / $m;
	$h = intval($_REQUEST['h']);
	$h = $h ? $h : intval($d + 20 + 20);

	$legend = 200;
	$w = (4 / 3) * $h + $legend;
	$cw = intval($w - 60) - $legend;
	$ch = intval($h - 30 - 20);
	$cx = 40;
	$cy = intval((($h - 20) - $ch) / 2);
	$px = 0;
	$py = 0;
	$ty = $cy + $ch;

	$img   = ImageCreateTrueColor($w, $h);
	$black = ImageColorAllocate($img, 0, 0, 0);
	$gray = ImageColorAllocate($img, 128, 128, 128);
	$lite = ImageColorAllocate($img, 200, 200, 200);
	$dot1 = ImageColorAllocate($img, 0, 0, 255);
	$dot2 = ImageColorAllocate($img, 255, 80, 80);
	$dot3 = ImageColorAllocate($img, 0, 0, 0);
	$white =ImageColorAllocate($img, 255, 255, 255);
	$back  = $white;
	$line = array(
		ImageColorAllocate($img, 0, 0, 0)
	, ImageColorAllocate($img, 100, 100, 100)
	);
	$red  = array(
		ImageColorAllocate($img, 255, 80, 80)
	, ImageColorAllocate($img, 255, 150, 150)
	);
//	imagecolortransparent($img, $back);
	ImageFill($img, 0, 0, $back);
	ImageRectangle($img, 0, 0, $w - 1, $h - 1, $black);
	imageline($img, $cx, $ty, $cx + $cw, $ty - $ch, $gray);



	foreach ($leech as $idx => $record) {
		$tap = intval(intval($record['progress']) * $ch / 100);
		$hit = intval($cw * (((float)$record['time']) / $t));
		$label = $tap - $font / 2;//$py - ($py - $hit) / 2;
		$c = $x[$idx] ? $red : $line;
		imageline($img, $cx, $ty - $tap, $cx + $cw, $ty - $tap, $lite);
		imagesetthickness($img, 2);
		imageline($img, $cx + $px - 1, $ty - $py - 1, $cx + $hit + 1, $ty - $tap + 1, $c[1]);
		imageline($img, $cx + $px + 1, $ty - $py + 1, $cx + $hit - 1, $ty - $tap - 1, $c[1]);
		imageline($img, $cx + $px, $ty - $py, $cx + $hit, $ty - $tap, $c[0]);
		imagettftext ($img, $font, 0, $cx + $cw + 5, $ty - $label, $black, ROOT. "/_engine/comic.ttf", $record['uid']);
		imagettftext ($img, $font, 0, 3, $ty - $label, $black, ROOT. "/_engine/comic.ttf", intval(1000 * (float)$record['time']) / 1000 );
		imagesetthickness($img, 1);
		$py = $tap;
		$px = $hit;
	}
	$px = 0;
	$py = 0;
	foreach ($leech as $idx => $record) {
		$tap = intval(intval($record['progress']) * $ch / 100);
		$hit = intval($cw * (((float)$record['time']) / $t));
		imagefilledellipse($img, $cx + $hit, $ty - $tap, 7, 7, $dot1);
		imagefilledellipse($img, $cx + $hit, $ty - $tap, 5, 5, $dot2);
		$py = $tap;
		$px = $hit;
	}
	$px = 0;
	$py = 0;
	foreach ($leech as $idx => $record) {
		$tap = intval(intval($record['progress']) * $ch / 100);
		$hit = intval($cw * (((float)$record['time']) / $t));
		if ($x[$idx]) {
			$v = (float)$record['delta'];
			$p = intval(100 * ($v / $t)) . '%';
			$s = imagettfbbox($font, 0, ROOT. "/_engine/comic.ttf", $p);
			$tw = $s[2] - $s[0];
			$x1 = $cx + $px;
			$y1 = $ty - $py;
			$x2 = $cx + $hit;
			$y2 = $ty - $tap;
			$x1 = $x1 + ((($x2 - $x1) - $tw) / 2);
			$y1 = $y1 + ((($y2 - $y1) - $font) / 2);
			imagettftext ($img, $font, 0, $x1-1, $y1, $lite, ROOT. "/_engine/comic.ttf", $p);
			imagettftext ($img, $font, 0, $x1+1, $y1, $lite, ROOT. "/_engine/comic.ttf", $p);
			imagettftext ($img, $font, 0, $x1, $y1+1, $lite, ROOT. "/_engine/comic.ttf", $p);
			imagettftext ($img, $font, 0, $x1, $y1-1, $lite, ROOT. "/_engine/comic.ttf", $p);
			imagettftext ($img, $font, 0, $x1, $y1, $black, ROOT. "/_engine/comic.ttf", $p);
		}
		$py = $tap;
		$px = $hit;
	}
	$d = 0;
	foreach ($x as $v)
		$d += $v;

	$v = intval($d * 1000) / 1000;
	$p = intval(100 * ($d / $t));
	$c = count($x);
	$p = "$v sec ($c longest intervals) covered $p% of execution time";
	imagettftext ($img, $font, 0, $cx, $cy + $ch + $font * 2, $black, ROOT. "/_engine/comic.ttf", $p);
	$p = "URI: " . $data['data']['uri'];
	imagettftext ($img, 8, 0, $cx, $h - 8, $black, ROOT. "/_engine/comic.ttf", $p);
	$p = "DEVITER CMS © ankhzet@gmail.com";
	imagettftext ($img, 8, 0, $w - strlen($p) * 6, $h - 8, $gray, ROOT. "/_engine/comic.ttf", $p);

	header('Content-Type: image/png');
	imagetruecolortopalette($img, true, 16);
	imagepng($img);
?>
