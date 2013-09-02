<?php
	require_once '../base.php';

	if (User::ACL() < ACL::ACL_MODER) JSON_result(JSON_Fail, 'access denied');

	require_once 'core_visitors.php';

	$t = 60 * 60 * 24;
	$time = time();
	$l = ($days = post('days')) ? $t * post('days') : $time;

	$f = array('1');

	if ($ip = post('ip'))
		$f[] = "inet_ntoa(`ip`) like '%$ip%'";

	$dbc = msqlDB::o();

	$f = array('1');

	if ($days = $time - $l)
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
		$u[$time] = isset($u[$time])
			? array($u[$time][0] + 1, array_unique(array_merge($u[$time][1], array($ip))))
			: array(1, array($ip));
		if (!isset($t[$ip])) $t[$ip] = array($time, $time);
		$t[$ip][0] = min($t[$ip][0], $time);
		$t[$ip][1] = max($t[$ip][1], $time);
	}


	ksort($u);
	$k = array_keys($u);
	$lot = count($k) ? $k[0] : 0;
	$hit = count($k) ? $k[count($k) - 1] : $lot;
/*	$m = 0;
	foreach ($k as $time) {
		$c = 0;
		foreach ($t as $ip => $times)
			if ($times[0] <= $time && $times[1] >= $time)
				$c++;
		$m = max($m, $c);
	}*/

	$tt = ($hit - $lot) ? $hit - $lot : 1;

	$days = uri_frag($_REQUEST, 'days', 1);
	$parts = ($days <= 1) ? 24 : 4 * $days;
	if ($parts > 100) $parts = 100;

	$o = $tt / $parts;
	if ($o < 1) $o = 1;
	$y = array();

	foreach ($u as $time => $v) {
		$_ = intval(($time - $lot) / $o);
		if (!isset($y[$_]))
			$y[$_] = array(1, $v[0], $v[1]);
		else {
			$y[$_][0]++;
			$y[$_][1]+= $v[0];
			$y[$_][2] = array_unique(array_merge($y[$_][2], $v[1]));
		}
	}

//	debug2($y);
	$m = 0;
	$ipmax = 0;
	$ips = array();
	foreach ($y as $slice => $v) {
		$ips[$slice] = count($v[2]);
		$y[$slice] = $v[1];//ceil($v[1] / $v[0]);
		$m = max($m, $y[$slice]);
		$ipmax = max($ipmax, $ips[$slice]);
	}
//	debug2($y);
//	debug2($ips);

	$k = array_keys($y);
	$lo = $y[0];
	$hi = count($y) ? $y[$k[count($y) - 1]] : $lo;
	$t = ($hi - $lo) ? $hi - $lo : 1;

	$k = array_keys($y);
	$c = count($k) - 1;
	$i = 0;
	$e = array();
/** /	$tmp = $y;
	foreach ($tmp as $slice => $visitors) {
		$p1 = $i - 1; $prev1 = ($p1 < 0) ? $tmp[$k[0]]  : $tmp[$k[$p1]];
		$n1 = $i + 1; $next1 = ($n1 >$c) ? $tmp[$k[$c]] : $tmp[$k[$n1]];
		$p1 = $i - 2; $prev1 = ($p1 < 0) ? $tmp[$k[0]]  : $tmp[$k[$p1]];
		$n1 = $i + 2; $next1 = ($n1 >$c) ? $tmp[$k[$c]] : $tmp[$k[$n1]];

		$y[$slice] = ($prev2 + $prev1 + $visitors + $next1 + $next2) / 5.0;
//		$e[$slice] = array($prev, $visitors, $next, $y[$slice]);
	}/**/
//		debug2($e);

//	debug2($y);
//	debug2($i);
//	debug2($u);
//	debug2(array($lo, $hi));


	$img_denom = 6;
	$heat_denom = 3;
	$font = 8;
	$d = ($font + 1) * ($parts + 1);
	$w = uri_frag($_REQUEST, 'w', 600);//intval($d + 60));
	if ($w < 200) $w = 200;
	$w *= $img_denom;
	$font = 8 * $img_denom;
	$d = ($font + 1) * ($parts + 1);

	$legend = intval($font * 2);
	$h = 400 * $img_denom;//(10 / 20) * ($w - 60 * $img_denom) + 40 * $img_denom;
//	debug2(array($w, $h));
	$cw = intval($w - 60 * $img_denom);
	$ch = intval($h - 40 * $img_denom - $legend);
	$cx = 20 * $img_denom;
	$cy = intval((($h - 20 * $img_denom) - $ch - $legend) / 2);
	$ty = $cy + $ch;

	$img   = ImageCreate($w, $h);
//	imagealphablending($img, true);
	$black = ImageColorAllocate($img, 0, 0, 0);
	$gray = ImageColorAllocate($img, 128, 128, 128);
	$lite = ImageColorAllocate($img, 200, 200, 200);
	$dot1 = ImageColorAllocate($img, 0, 0, 255);
	$dot2 = ImageColorAllocate($img, 255, 80, 80);
	$dot3 = ImageColorAllocate($img, 0, 0, 0);
	$white =ImageColorAllocate($img, 255, 255, 255);
	$back  = $white;
	$line = array(
		ImageColorAllocate($img, 10, 10, 10)
	, ImageColorAllocate($img, 200, 200, 200)
	);
	$red = array(
		ImageColorAllocate($img, 50, 50, 150)
	, ImageColorAllocate($img, 190, 190, 250)
	);
//	imagecolortransparent($img, $back);
	imagesetthickness($img, 1 * $img_denom);
	ImageFill($img, 0, 0, $back);
	ImageRectangle($img, 0, 0, $w - $img_denom + 1, $h - $img_denom + 1, $black);
//	imageline($img, $cx, $ty, $cx + $cw, $ty - $ch, $gray);


//	imageantialias($img, true);
	$ff = ROOT . "/_engine/comic.ttf";

	global $cx, $cw, $dot1, $dot2, $img, $img_denom, $heat_denom, $tt;
	function graph(&$pts, &$pairs, $parts, $line, $ty, $ch, $m, $draw = true) {
		global $cx, $cw, $dot1, $dot2, $img, $img_denom, $heat_denom, $tt;
		$px = 0;
		$py = -1;
		foreach ($pts as $slice => $visitors) {
			$time = $tt * $slice / $parts;
			$tap = intval($ch * $visitors / $m);
			if ($py < 0) $py = $tap;
			$hit = intval($cw * (float)$time / $tt);
			$pairs[$slice] = array($cx + $px, $ty - $py, $cx + $hit, $ty - $tap, $time, $visitors);
			$py = $tap;
			$px = $hit;
		}

		$px = array();
		$py = array();
		foreach ($pairs as $pair) {
			$px[] = $pair[0] - $cx;
			$py[] = $ty - $pair[1];
		}

		require_once 'core_csplines.php';
		$s = new CSpline();
		$s->build($px, $py);
//	debug2(array($px, $py));
//	debug2($s);

		$p = array();
		$n = 1000;
		$i = 0;
		while ($i++ < $n) {
			$nx = $cw * $i / $n;
			$ny = $s->f($nx);
			$p[$cx + $nx] = $ty - $ny;
		}

		$px = $cx;
		$py = -1;
		imagesetthickness($img, $heat_denom * $img_denom);
		$half_denom = $img_denom / $heat_denom;
		foreach ($p as $x => $y) {
			if ($py < 0) $py = $y;
//			imageline($img, $px, $py, $x, $y, $line[1]);
			imageline($img, $px - $half_denom, $py - $half_denom, $x + $half_denom, $y + $half_denom, $line[1]);
			imageline($img, $px + $half_denom, $py + $half_denom, $x - $half_denom, $y - $half_denom, $line[1]);
			$px = $x;
			$py = $y;
		}

		$px = $cx;
		$py = -1;
		imagesetthickness($img, 1 * $img_denom);
		foreach ($p as $x => $y) {
			if ($py < 0) $py = $y;
			imageline($img, $px, $py, $x, $y, $line[0]);
			$px = $x;
			$py = $y;
		}

		foreach ($pairs as $c) {
			imagefilledellipse($img, $c[2], $c[3], 7 * $img_denom, 7 * $img_denom, $dot1);
			imagefilledellipse($img, $c[2], $c[3], 5 * $img_denom, 5 * $img_denom, $dot2);
		}
	}

	$pairs = array();
	$nty = $ty - $ch * 0.3;
	graph($y, $pairs, $parts, $line, $nty, $ch * 0.7, $m, false);


	foreach ($pairs as $c) {
		$label = $c[3] + $font;
		$time = $c[4] + $lot;
		imagettftext ($img, $font, 0, $cx + $cw + 5 * $img_denom, $label - $font / 2, $black, $ff, $c[5]);
	}


	$pairs = array();
	graph($ips, $pairs, $parts, $red, $ty + $font * 2, $ch * 0.3, $ipmax);
	foreach ($pairs as $c) {
		$label = $c[3] + $font;
		$time = $c[4] + $lot;
		imagettftext ($img, $font, 0, $cx + $cw + 5 * $img_denom, $label - $font / 2, $black, $ff, $c[5]);
	}

	$text = ($days <= 1) ? date('H:i:s', $lot) : date('d.m.Y', $lot);
	$data = imageftbbox ($font, 0, $ff, $text);
	$tw = $data[2] - $data[0] + 5 * $img_denom;
	$_parts = $parts;
	$interval = $cw / $_parts;
	while ($interval <= $tw) {
		$_parts /= 2;
		if ($_parts < 1)
			break;
		$interval = $cw / $_parts;
	}
	$parts = intval($_parts);
	$interval = $cw / $_parts;

	while ($_parts-- > 0) {
		$i = ($parts - $_parts);
		$time = $lot + $i * ($tt / $parts);
		$tx = $cx + $i * $interval;
		$label = $ty + $font + $legend;
		$_hit = $tx - $tw + $img_denom * 10;
		$text = ($days <= 1) ? date('H:i:s', $time) : date('d.m.Y', $time);
		imagettftext ($img, $font, 0, $_hit + $img_denom, $label, $white, $ff, $text);
		imagettftext ($img, $font, 0, $_hit - $img_denom, $label, $white, $ff, $text);
		imagettftext ($img, $font, 0, $_hit, $label + $img_denom, $white, $ff, $text);
		imagettftext ($img, $font, 0, $_hit, $label - $img_denom, $white, $ff, $text);
		imagettftext ($img, $font, 0, $_hit, $label, $black, $ff, $text);
	}

	$p = "DEVITER CMS © ankhzet@gmail.com";
	imagettftext ($img, 8 * $img_denom, 0, $w - strlen($p) * 8 * $img_denom, $h - 16, 8 * $img_denom, $ff, $p);

	header('Content-Type: image/png');
	$img2 = ImageCreateTrueColor ($w / $img_denom, $h / $img_denom);
	imagecopyresampled ($img2, $img, 0, 0, 0, 0, $w / $img_denom, $h / $img_denom, $w, $h);
	imagetruecolortopalette($img2, true, 128);
	imagepng($img2);
?>
