<?php

	class PageUtils {
		public static function getPageStorage($page) {
			return 'cms://cache/pages/' . $page;
		}

		public static function getPageContents($page, $version = 'last', $clean = true) {
			$storage = self::getPageStorage($page);
			$contents = @file_get_contents("$storage/$version.html");
			if (($contents !== false) && ($contents != '')) {
				$contents1 = @gzuncompress/**/($contents);
				if ($contents1 !== false)
					$contents = $contents1;
				else
					@file_put_contents("$storage/$version.html", gzcompress($contents));

				if ($clean)
					$contents = (self::prepareForGrammar($contents, true));
			} else
				return false;

			return $contents;
		}

		public static function prepareForGrammar($c, $cleanup = false) {
			if ($cleanup) {
				$c = preg_replace('"<([^\/[:alpha:]])"i', '&lt;\1', $c);
//				$c = str_replace('&nbsp;', ' ', $c);

				$c = preg_replace('"<p([^>]*)?>(.*?)<dd>"i', '<p\1>\2<dd>', $c);
				$c = preg_replace('"(</?(td|tr|table)[^>]*>)'.PHP_EOL.'"', '\1', $c);
				$c = preg_replace('"'.PHP_EOL.'(</?(td|tr|table)[^>]*>)"', '\1', $c);
				$c = str_replace(array("\r", "\n", '</dd>'), '', $c);
				$c = str_replace(array('<dd>', '<br>', '<br/>', '<br />'), PHP_EOL, $c);
				$c = preg_replace('"<p\s*>([^<]*)</p>"i', '<p/>\1', $c);
				$c = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $c);
				$c = preg_replace('"<(\w+)[^>]*>((\s|\&nbsp;)*)</\1>"', '\2', $c);
				$c = preg_replace('"</(\w+)>((\s|\&nbsp;)*)?<\1>"i', '\2', $c);
				$c = preg_replace('"<font([^<]*)color=\"?black\"?([^<]*)>"i', '<font\1\2>', $c);
				$c = preg_replace('"<font\s*>(!?</font>)</font>"i', '\1', $c);
				$c = preg_replace('"<(font|span)\s*(lang=\"?[^\"]+\"?)\s*>([^<]*)</\1>"i', '\3', $c);

//				$c = preg_replace('/ {3,}/', '  ', $c);

			} else
				$c = str_replace('<br />', PHP_EOL, $c);

/*			$idx = 0;
			$p = 0;
			while (preg_match('"<(([\w\d]+)([^>]*))>"', substr($c, $p), $m, PREG_OFFSET_CAPTURE)) {
				$p += intval($m[0][1]);
				$sub = $m[0][0];
				if (strpos($sub, 'class="pin"') === false) {
					$idx++;
					$tag = $m[2][0];
					$attr = $m[3][0];
					$u = "<$tag node=\"$idx\"$attr>";
					$c = substr_replace($c, $u, $p, strlen($sub));
					$p += strlen($u);
				} else
					$p += strlen($sub);
			}*/
			$p = 0;
			while (preg_match('|<img [^>]*(src=(["\']?))/[^\2>]+\2[^>]*(>)|i', substr($c, $p), $m, PREG_OFFSET_CAPTURE)) {
				$p += intval($m[1][1]);
				$u = $m[1][0] . 'http://samlib%2Eru';
				$c = substr_replace($c, $u, $p, strlen($m[1][0]));
				$p += strlen($u) + intval($m[3][1]) - intval($m[1][1]) - 5;
			}
			return /*$cleanup ? $c : */str_replace(PHP_EOL, PHP_EOL . '<br/>', $c);
		}

		public static function traceMark($uid, $trace, $page, $author) {
			$trace  = $uid ? $trace : -1;
			$trace_f = intval(!($trace > 0));
			$traced = array( 1 => 'traced', 0 => 'untraced', -1 => 'nottraced');
			$caption = $uid ? Loc::lget(($trace > 0) ? 'untrace' : 'trace') : Loc::lget('login');
			$trace  = Loc::lget($color  = $traced[$trace]);
			$action = (!$uid)
			? "<br />[ <a href=\"/user/login?url=/authors/id/$author\">$caption</a> ]"
			: "<br />[ <a href=\"/updates/trace/$author/$page?trace=$trace_f\">$caption</a> ]";
			return "<div class=\"trace-mark $color\"><span>$trace$action</span></div>";
		}

		public static function decodeVersion($r, $offset = 0, $diff = false) {
			if ($version = post_int('version'))
				return $version;

			$date = uri_frag($r, $offset, 0, false);
			if (strpos($date, ',') === false) {
				$date = explode('-', $date);
				$time = explode('-', uri_frag($r, $offset + 1, 0, false));
				$t1 = mktime($time[0], $time[1], $time[2], $date[1], $date[0], $date[2]);
				if ($diff) {
					$offset += 2;
					$date = explode('-', uri_frag($r, $offset + 0, 0, false));
					$time = explode('-', uri_frag($r, $offset + 1, 0, false));
					$t2 = mktime($time[0], $time[1], $time[2], $date[1], $date[0], $date[2]);
					$t = array($t1, $t2);
				} else
					$t = $t1;
			} else
				$t = explode(',', $date);
			return $t;
		}

		public static function isDiffMode() {
			if (isset($_REQUEST['diff_always'])) {
				$diff_mode = intval($_REQUEST['diff_always']);
				$t = time() + ($diff_mode ? 1 : -1) * 2592000;
				$host = $_SERVER['HTTP_HOST'];
				preg_match('/(.*\.|^)([^\.]+\.[^\.]+)$/i', $host, $m);
				setcookie('diff_mode', $diff_mode, $t, "/", '.' . $m[2]);
				$_REQUEST['diff_mode'] = $diff_mode;
			} else {
				$dm_cook = isset($_COOKIE['diff_mode']);
				$dm_post = isset($_GET['diff_mode']);
				$diff_mode = $dm_post ? intval($_GET['diff_mode']) : ($dm_cook ? intval($_COOKIE['diff_mode']) : 0);
			}
			return $diff_mode;
		}

		public static function fetchVersions($storage) {
			$p = array();
			$d = is_dir($storage) ? @dir($storage) : null;
			if ($d)
				while (($entry = $d->read()) !== false)
					if (is_file($storage . '/' . $entry) && ($version = intval(basename($entry, '.html'))))
						$p[] = $version;
			return $p;
		}

		public static function buildCalendar($page, $versions, $lastseen, $storage) {
			$_tday = 60 * 60 * 24; // 1 day in seconds

			$result = '';
			$updates = explode(',', Loc::lget('updates'));
			$update_base = array_shift($updates);
			$read = Loc::lget('view');
			$row['diff'] = Loc::lget('diff');
			$download = Loc::lget('download');
			$delete = Loc::lget('delete');
			$ccc = count($versions);
			$daynames = '';
			while ($versions && ($ccc-- > 0)) {
				rsort($versions);

				//datetime of current update version
				$date = getdate($versions[0]);
//				debug($date);
				$month = intval($date['mon']);
				$day = intval($date['mday']);
				$year = intval($date['year']);

				//timestamp of the beginning of month
				$first= mktime(0, 0, 0, $month, 1, $year);
				//timestamp of last day of month
				$last = mktime(0, 0, 0, $month + 1, 0, $year);
				$last2= mktime(0, 0, 0, $month + 1, 1, $year);

				//days in current version related month
				$days = intval(gmdate('d', $last));
				//days in previous month
				$prev = intval(gmdate('d', mktime(0, 0, 0, $month, 0, $year)));


				//first week-day index of month
				$date = getdate($first);
				$fday = intval($date['wday']);
				$fday = ($fday == 0) ? 6 : $fday - 1;

				//last week-day index of month
				$date = getdate($last);
				$lday = intval($date['wday']);
				$lday = ($lday == 0) ? 6 : $lday - 1;

				//last day of previous mont in current month-slot
				$pred = $prev - $fday + 1;
				//last day of next mont in current month-slot
				$succ = 6 - $lday;

				// fill in days of next month
//				if (count($d) + $succ < 6 * 7) $succ += 7;


				//fill in days of previous month
				$d = array();
//				while ($pred++ <= $prev)
//					$d[] = $first - ($prev - $pred + 2) * $_tday;

				//fill in days of current month
				$_date = getdate($first);
				$_month = intval($_date['mon']);
				$_year = intval($_date['year']);
				$f = 0 - $fday;
				while (++$f <= $days + $succ + 1)
					$d[] = mktime(0, 0, 0, $_month, $f, $_year);

//				$f = 1;
//				while ($f++ <= $succ)
//					$d[] = $first + ($days + $f - 2) * $_tday;


				$c = 8;
				$r = '';
				if (!$daynames) {
					$w = '';
					for ($i = 0; $i < 7; $i++) {
						$dn = strftime('%a', mktime(0, 0, 0, $month, $days + $succ - $c * 7 + $i + 2, $year));
						$w = "<td>" . mb_substr($dn, 0, 2) . "</td>" . $w;
					}
					$daynames = "<tr class=\"day-name\">$w</tr>\r\n";
				}

				$moder = User::ACL() >= ACL::ACL_MODER;

				while (($c-- > 0) && $d) {
					$w = '';

					for ($i = 0; $i < 7; $i++) {
						$day = array_shift($d); // day timastamp to process

						$_date = getdate($day);
						$_month = intval($_date['mon']);
						$_day = intval($_date['mday']);
						$_year = intval($_date['year']);

						//timestamp of the beginning of next day
						$nxt = mktime(0, 0, 0, $_month, $_day + 1, $_year);

						// is this day belongs to current month
						$current = !(($day < $first) || ($day >= $last2));
						$v = array();
						if ($current)
							foreach ($versions as $version) // foreach version
								if (($version < $day) || ($version >= $nxt))
									continue;  // if version don't belongs to current day - break
								else
									$v[] = $version;


						$current = $current ? '' : ' class="grey"';

						$dayname = date('j', $day);
						if ($v) {
							$versions = array_diff($versions, $v);
							$m = ($cnt = count($v)) > 1;

								$e = array();
								foreach ($v as $version) {
									$new = ($version > $lastseen) ? ' c-n' : '';
									$size = fs(@filesize("$storage/$version.html"));
									$time = date('H:i:s', $version);
									$version = date('d-m-Y/H-i-s', $version);
									$dlt = (!$moder) ? '' : "| <a href=\"/pages/remove/$page/$version\">$delete</a>";
									$e[] = "
<li>
	&raquo; <x class=\"$new\">$time, $size</x>:
	<a href=\"/pages/version/$page/view/$version\">$read</a>
	| <a href=\"/pages/download/$page/$version\">$download</a>
	$dlt
</li>";
								}
								$v = join('', $e);
								$m = ' c-m';
								$action = '';
								$dayname2 = date('j-m-Y', $day);
								$up_form = aaxx($cnt, $update_base, $updates);
								$dayname = "<a>$dayname</a><div class=\"c-m-v\"><b>$dayname2</b>: $cnt $up_form<br /><ul>$v</ul></div>";

							$current = " class=\"c-v{$m}{$new}\"";
						}

						$w = "<td{$current}>$dayname</td>" . $w;
					}

					$r = "<tr>$w</tr>\r\n" . $r;
				}

				$cur = Loc::lget('updates_by') . ' ' . strftime('%B, %Y', mktime(0, 0, 0, $month, 1, $year));
				$h = "<tr><td colspan=\"7\" class=\"calendar-header\">$cur</td></tr>\r\n";
				$result .= "\r\n$h\r\n$daynames\r\n$r";
			}


			if (count($versions))
				echo "oO?"; // omg, how did that happened

			return "\r\n<table class='calendar'>\r\n$result\r\n</table>\r\n";
		}

	}