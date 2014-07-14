<?php
	require_once '../base.php';

	if (User::ACL() < ACL::ACL_MODER) JSON_result(JSON_Fail, 'access denied');

	require_once 'core_visitors.php';

	$dayLength = 60 * 60 * 24;
	$time = time();
	$days = post('days');
	$l = $days ? $dayLength * $days : $time;

	$f = array();

	if ($ip = post('ip'))
		$f[] = "inet_ntoa(`ip`) like '%$ip%'";

	$dbc = msqlDB::o();

	if ($after = $time - $l)
		$f[] = "`time` >= $after";

	switch ($bots = uri_frag($_REQUEST, 'bots', 1)) {
	case 0: break;
	case 1: $f[] = "`type` >= 0"; break;
	case 2: $f[] = "`type` < 0"; break;
	}

	$q = join(' and ', $f);

	$s = $dbc->select('visitors', $q, '`ip`, `time`');
	$f = array();
	$rc = mysql_num_rows($s);
	for ($r = 0; $r < $rc; $r++)
		$f[$r] = mysql_fetch_row($s);

	$latest = time();
	$newest = 0;
	foreach ($f as $row) {
		$time = intval($row[1]);
		$latest = min($latest, $time);
		$newest = max($newest, $time);
	}

	$fromLatestToNewest = (($newest - $latest) > 0) ? $newest - $latest : 1;

	$days = uri_frag($_REQUEST, 'days', $fromLatestToNewest / $dayLength);

	$parts = 32;
	if ($days <= 365) $parts = $days / 16;
	if ($days <= 180) $parts = $days / 8;
	if ($days <= 90) $parts = $days / 4;
	if ($days <= 31) $parts = $days;
	if ($days <= 7 ) $parts = $days * 4;
	if ($days == 1 ) $parts = 24;
	$parts = intval($parts);

	$secondsPerPart = max(1, $fromLatestToNewest / $parts);

	$statistics = array();
	foreach ($f as $row) {
		$ip = intval($row[0]);
		$slice = intval((intval($row[1]) - $latest) / $secondsPerPart);
		$chunk = &$statistics[$slice];
		if (isset($statistics[$slice])) {
			$chunk[0]++;
			if (array_search($ip, $chunk[1]) === false)
				$chunk[1][] = $ip;
		} else
			$chunk = array(1, array($ip));
	}

	foreach ($statistics as &$chunk)
		$chunk[1] = count($chunk[1]);

	ksort($statistics);

	$maxHits = 0;
	$ipmax = 0;
	$uniques = array();
	$pagehits = array();
	foreach ($statistics as $slice => &$hitData) {
		$pagehits[$slice] = $hitData[0];
		$uniques[$slice] = $hitData[1];
		$maxHits = max($maxHits, $hitData[0]);
		$ipmax = max($ipmax, $hitData[1]);
	}

	/* ========== Render =========== */

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

	global $cx, $cw, $dot1, $dot2, $img, $img_denom, $heat_denom, $fromLatestToNewest, $secondsPerPart;
	function graph(&$pts, &$pairs, $line, $ty, $ch, $max, $draw = true) {
		global $cx, $cw, $dot1, $dot2, $img, $img_denom, $heat_denom, $fromLatestToNewest, $secondsPerPart;
		$px = 0;
		$py = -1;
		foreach ($pts as $slice => $visitors) {
			$time = $secondsPerPart * $slice;
			$tap = intval($ch * $visitors / $max);
			if ($py < 0) $py = $tap;
			$hit = intval($cw * (float)$time / $fromLatestToNewest);
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
	graph($pagehits, $pairs, $line, $nty, $ch * 0.7, $maxHits, false);


	foreach ($pairs as $c) {
		$label = $c[3] + $font;
		$time = $c[4] + $latest;
		imagettftext ($img, $font, 0, $cx + $cw + 5 * $img_denom, $label - $font / 2, $black, $ff, $c[5]);
	}


	$pairs = array();
	graph($uniques, $pairs, $red, $ty + $font * 2, $ch * 0.3, $ipmax);
	foreach ($pairs as $c) {
		$label = $c[3] + $font;
		$time = $c[4] + $latest;
		imagettftext ($img, $font, 0, $cx + $cw + 5 * $img_denom, $label - $font / 2, $black, $ff, $c[5]);
	}

	$text = ($days <= 1) ? date('H:i:s', $latest) : date('d.m.Y', $latest);
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
		$time = $latest + $i * $secondsPerPart;
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
