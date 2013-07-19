<?php
	require_once '../base.php';

	if (User::ACL() < ACL::ACL_MODER) JSON_result(JSON_Fail, 'access denied');

	require_once 'core_visitors.php';

	$t = 60 * 60 * 24;
	$dbc = msqlDB::o();

	$f = array('1');

	if ($days = time() - $t * post('days'))
		$f[] = "`time` >= $days";

	switch ($bots = post('bots')) {
	case 0: break;
	case 1: $f[] = "`type` >= 0"; break;
	case 2: $f[] = "`type` < 0"; break;
	}

	$q = join(' and ', $f);

	$s = $dbc->select('visitors', $q, '`ip`, `type`, `ua`, `os`, `time`');
	$f = $dbc->fetchrows($s);
	$i = array();
	$t = array();
	$u = array();
	foreach ($f as &$row) {
		$i[$ip = intval($row['ip'])] = $row;
		$time = intval($row['time']);
		$u[$time] = 0;
		if (!isset($t[$ip])) $t[$ip] = array($time, $time);
		$t[$ip][0] = min($t[$ip][0], $time);
		$t[$ip][1] = max($t[$ip][1], $time);
	}

	ksort($u);
	$k = array_keys($u);
	$lot = $k[0];
	$hit = count($k) ? $k[count($k) - 1] : $lo;
	$m = 0;
	foreach ($k as $time) {
		$c = 0;
		foreach ($t as $ip => $times)
			if ($times[0] <= $time && $times[1] >= $time)
				$c++;
		$u[$time] = $c;
		$m = max($m, $c);
	}

	$tt = ($hit - $lot) ? $hit - $lot : 1;

	$parts = 24;

	$o = $tt / $parts;
	if ($o < 1) $o = 1;
	$y = array();

	foreach ($u as $time => $v) {
		$_ = intval(($time - $lot) / $o);
		if (!isset($y[$_]))
			$y[$_] = array(1, $v);
		else {
			$y[$_][0] ++;
			$y[$_][1] += $v;
		}
	}

	$m = 0;
	foreach ($y as $slice => $v) {
		$y[$slice] = ceil($v[1] / $v[0]);
		$m = max($m, $y[$slice]);
	}

	$k = array_keys($y);
	$lo = $y[0];
	$hi = count($y) ? $y[count($k) - 1] : $lo;
	$t = ($hi - $lo) ? $hi - $lo : 1;


//	debug2($y);
//	debug2($i);
//	debug2($u);
//	debug2(array($lo, $hi));

	$img_denom = 4;
	$font = 8;
	$d = ($font * 8) * $m;
	$h = uri_frag($_REQUEST, 'h', intval($d + 20 + 20));
	if ($h < 200) $h = 200;
	$h *= $img_denom;
	$font = 8 * $img_denom;
	$d = ($font * 8) * $m;

	$legend = 0;
	$w = (20 / 10) * $h + $legend;
	$cw = intval($w - 60 * $img_denom) - $legend;
	$ch = intval($h - 30 * $img_denom - 20 * $img_denom);
	$cx = 20 * $img_denom;
	$cy = intval((($h - 20 * $img_denom) - $ch) / 2);
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
	$c = array(
		ImageColorAllocate($img, 50, 50, 50)
	, ImageColorAllocate($img, 190, 190, 190)
	);
//	imagecolortransparent($img, $back);
	imagesetthickness($img, 1 * $img_denom);
	ImageFill($img, 0, 0, $back);
	ImageRectangle($img, 0, 0, $w - $img_denom + 1, $h - $img_denom + 1, $black);
//	imageline($img, $cx, $ty, $cx + $cw, $ty - $ch, $gray);


//	imageantialias($img, true);
	$ff = ROOT . "/_engine/comic.ttf";
	$px = 0;
	$py = -1;
	foreach ($y as $slice => $visitors) {
		$time = $tt * $slice / $parts;
		$tap = intval($ch * $visitors / $m);
		if ($py < 0) $py = $tap;
		$hit = intval($cw * (float)$time / $tt);
//		debug2(array($hit, $tap));
//		imageline($img, $cx, $ty - $tap, $cx + $cw, $ty - $tap, $lite);
		imagesetthickness($img, 2 * $img_denom);
		imageline($img, $cx + $px - $img_denom, $ty - $py - $img_denom, $cx + $hit + $img_denom, $ty - $tap + $img_denom, $c[1]);
		imageline($img, $cx + $px + $img_denom, $ty - $py + $img_denom, $cx + $hit - $img_denom, $ty - $tap - $img_denom, $c[1]);
		imagesetthickness($img, 1.5 * $img_denom);
		imageline($img, $cx + $px    , $ty - $py    , $cx + $hit    , $ty - $tap    , $c[0]);
		$py = $tap;
		$px = $hit;
	}
	$px = 0;
	$py = -1;
	foreach ($y as $slice => $visitors) {
		$time = $tt * $slice / $parts;
		$tap = intval($ch * $visitors / $m);
		if ($py < 0) $py = $tap;
		$hit = intval($cw * (float)$time / $tt);
//		debug2(array($hit, $tap));
		$label = $tap - $font / 2;
		imagettftext ($img, $font, 0, $cx + $cw + 5 * $img_denom, $ty - $label, $black, $ff, $visitors);
		$text = date('H:i:s', $time + $lot);
		$_hit = $hit - $font / 2;
		imagettftext ($img, $font, -90, $cx + $_hit + $img_denom, $ty - $label + $font * 2, $white, $ff, $text);
		imagettftext ($img, $font, -90, $cx + $_hit - $img_denom, $ty - $label + $font * 2, $white, $ff, $text);
		imagettftext ($img, $font, -90, $cx + $_hit, $ty - $label + $font * 2 + $img_denom, $white, $ff, $text);
		imagettftext ($img, $font, -90, $cx + $_hit, $ty - $label + $font * 2 - $img_denom, $white, $ff, $text);
		imagettftext ($img, $font, -90, $cx + $_hit, $ty - $label + $font * 2, $black, $ff, $text);
		$py = $tap;
		$px = $hit;
	}
	$px = 0;
	$py = 0;
	foreach ($y as $slice => $visitors) {
		$time = $t * $slice / $parts;
		$tap = intval($ch * $visitors / $m);
		if ($py < 0) $py = $tap;
		$hit = intval($cw * (float)$time / $t);
		imagefilledellipse($img, $cx + $hit, $ty - $tap, 7 * $img_denom, 7 * $img_denom, $dot1);
		imagefilledellipse($img, $cx + $hit, $ty - $tap, 5 * $img_denom, 5 * $img_denom, $dot2);
		$py = $tap;
		$px = $hit;
	}
	$p = "DEVITER CMS © ankhzet@gmail.com";
	imagettftext ($img, 8 * $img_denom, 0, $w - strlen($p) * 8 * $img_denom, $h - 16, 8 * $img_denom, $ff, $p);

	header('Content-Type: image/png');
	$img2 = ImageCreateTrueColor ($w / $img_denom, $h / $img_denom);
	imagecopyresampled ($img2, $img, 0, 0, 0, 0, $w / $img_denom, $h / $img_denom, $w, $h);
	imagetruecolortopalette($img2, true, 256);
	imagepng($img2);
?>
