<?php
	require_once 'core_page.php';
	require_once 'core_authors.php';
	require_once 'core_history.php';
	require_once 'AggregatorController.php';

	class PagesController extends AggregatorController {
		protected $_name = 'pages';

		var $USE_UL_WRAPPER = false;
		var $MODER_EDIT   = '<span class="pull_right">[<a href="/{%root}/delete/{%id}">{%delete}</a>]</span>';
		var $ADD_MODER    = 0;
		var $EDIT_STRINGS = array();
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array();
		var $ID_PATTERN = '
			<div class="cnt-item">
				<div class="title">
					<span class="head">{%original}:</span>
					<span class="link">{%date}: {%time}</span>
					<span class="link samlib" style="float: none;"><a href="http://samlib.ru/{%autolink}/{%link}">/{%autolink}/{%link}</a></span>
				</div>
				<div class="text reader">
					{%content}
				</div>
			</div>
';
		var $LIST_ITEM  = '
					<div class="cnt-item">
						<div class="title">
							<span class="head"><a href="/authors/id/{%author}">{%fio}</a> - <a href="/{%root}/id/{%id}">{%title}</a>{%mark}{%moder}</span>
							<span class="link samlib"><a href="http://samlib.ru/{%autolink}/{%link}">{%autolink}/{%link}</a></span>

						</div>
						<div class="text">
							{%description}
						</div>
						<div class="text">
							<a href="/{%root}/version/{%id}">{%versions}</a> | <span class="size">{%size}KB</span>{%group}
						</div>
					</div>
';
		const VERSION_PATT = '
		<div class="cnt-item">
			<div class="title">
				<span class="head">{%timestamp}</span>
				<span class="link size">{%size}</span>
			</div>
			<div class="text">[
				<a href="/{%root}/version/{%page}/view/{%version}">{%view}</a>
			| <a href="/{%root}/download/{%page}/{%version}" noindex nofollow>{%download}</a>
			{%moderated}
				<span class="v-diff {%last}">&nbsp;| {%diff}: {%prev}</span>
			]</div>
		</div>
		';
		const VERSION_PATT2 = '
		<div class="cnt-item">
			<div class="title">
				<span class="head">{%timestamp}</span>
				<span class="link size">{%size}</span>
			</div>
			<div class="text">[
				<a href="/{%root}/version/{%page}/view/{%version}">{%view}</a>
			| <a noindex nofollow href="/{%root}/download/{%page}/{%version}">{%download}</a>
			{%moderated}
			]</div>
		</div>
		';

		const VERSION_MODER = '| <a href="/{%root}/remove/{%page}/{%version}">{%delete}</a>';

		const AGGR_PAGES = 0;
		const AGGR_AUTHO = 1;
		const AGGR_GROUP = 2;
		const AGGR_HISTO = 3;
		const AGGR_GRAMM = 4;

		function getAggregator($p = 0) {
			switch ($p) {
			case 0: return PagesAggregator::getInstance();
			case 1: return AuthorsAggregator::getInstance();
			case 2: return GroupsAggregator::getInstance();
			case 3: return HistoryAggregator::getInstance();
			case 4: return GrammarAggregator::getInstance();
			}
		}

		public function action() {
			$author = post_int('author');
			$group = post_int('group');
			$this->group_title = '';
			$this->author = '';
			if (!$author && $group) {
				$ga = $this->getAggregator(2);
				$g = $ga->get($group, '`author`, `title`');
				if ($g) {
					$g['title'] = str_replace(array('@ ', '@'), '', $g['title']);
					$this->group_title = $g['title'];
					$author = intval($g['author']);
				}
			}

			if ($author) {
				$this->query = '`author` = ' . $author . ($group ? ' and `group` = ' . $group : '');
				$this->link = '?author=' . $author . ($group ? '&group=' . $group : '');
				$g = $this->getAggregator(1);
				$a = $g->get($author);
				$this->author = $author;
				$this->author_fio = $a['fio'] ? $a['fio'] : '&lt;author&gt;';
				$this->author_link = $a['link'];
			}

			parent::action();
			if ($author)
				View::addKey('title'
				, "<a href=\"/{$this->_name}?author={$author}\">{$this->author_fio}</a> - "
				. ($group ? "<a href=\"/{$this->_name}?group={$group}\">{$this->group_title}</a>" : View::$keys['title'])
				);
		}

		public function actionPage($r) {
			if (!$this->author)
				locate_to('/authors');

			return parent::actionPage($r);
		}

		public function makeItem(&$aggregator, &$row) {
			html_escape($row, array('link'));

			$row['versions'] = Loc::lget('versions');
			$row['trace'] = Loc::lget('trace');
			$row['fio'] = $this->author_fio;
			$row['autolink'] = $this->author_link;

			$group = intval($row['group']);
			if ($group && !isset($this->groups[$group])) {
				$ga = $this->getAggregator(2);
				$g = $ga->get($group, '`id`, `title`');
				$g['title'] = str_replace(array('@ ', '@'), '', $g['title']);
				$this->groups[$group] = $g;
			}

			$uid = $this->user->ID();
			$page = $row['id'];
			$trace = -1;
			$ha = HistoryAggregator::getInstance();
			if ($uid) {
				$f = $ha->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` = $page limit 1", 'collumns' => '`trace` as `0`'));
				if ($f['total'])
					$trace = intval($f['data'][0][0]);
			}
			$row['mark'] = $aggregator->traceMark($uid, $trace, $page, $row['author']);

			$row['group'] = $group ? patternize(Loc::lget('group_patt'), $this->groups[$group]) : '';
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			$g = $this->getAggregator(1);
			$a = $g->get($author = $row['author'], '`fio`, `link`');
			$this->author_fio = $a['fio'] ? $a['fio'] : '&lt;author&gt;';
			$this->author_link = $a['link'];
			$page = intval($row['id']);
			$version = intval($row['utime']);
			$file = "cms://cache/pages/{$row['id']}/last.html";

			$ha = $this->getAggregator(3);
			$uid = $this->user->ID();
			$lastseen = uri_frag($_REQUEST, "ls_{$page}", -1);
			$trace    = -1;
			if ($uid) {
				$f = $ha->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` = $page limit 1", 'collumns' => '`trace`'));
				if ($f['total'])
					$trace = intval($f['data'][0]['trace']);
			}

			View::addKey('meta-keywords', "{$a['fio']}, {$row['title']}");
			View::addKey('meta-description', "{$a['fio']} - {$row['title']}. {$row['description']}");

			View::addKey('hint', $aggregator->traceMark($uid, $trace, $page, $author));

			View::addKey('title', "<a href=\"/authors/id/{$row['author']}\">{$this->author_fio}</a> - <a href=\"/pages/version/{$page}\">{$row['title']}</a>");
			html_escape($row, array('author', 'link'));
			$row['date'] = Loc::lget('date');
			$row['original'] = Loc::lget('original');
			$row['fio'] = $this->author_fio;
			$row['autolink'] = $this->author_link;
			$row['content'] = is_file($file) ? @file_get_contents($file) : false;
			if ($row['content'] !== false) {
				$c = @gzuncompress/**/($row['content']);
				if ($c !== false) $row['content'] = $c;
			} else
				$row['content'] = '&lt;no file&gt;';

			$row['content'] = $this->prepareForGrammar($row['content'], true);
			$row['content'] = mb_convert_encoding($row['content'], 'UTF-8', 'CP1251');

//			View::addKey('grammar', $this->fetchGrammarSuggestions(intval($row['id'])));
			View::addKey('preview', $row['content']);
			View::addKey('h_old', '');
			View::addKey('h_new', '');
			$this->view->renderTPL('pages/view');
			$this->updateTrace($page, $version);
			return '';//patternize($this->ID_PATTERN, $row);
		}

		function makeDetailHint($hint, $desc, $alink, $link) {
			$original = Loc::lget('original');
			$comments = Loc::lget('comments');
			$link1 = "$alink/$link";
			$link2 = str_replace('.shtml', '', $link1);

			$adiff = $this->isDiffMode();
			$ndiff = intval(!$adiff);
			$diffs = Loc::lget(($ndiff ? 'show' : 'hide') . '_diffs');
			$diffst = Loc::lget(($ndiff ? 'diffs' : 'calendar') . '_mode');
			$adiffs = Loc::lget('diff_' . ($adiff ? 'always' : 'newer'));

			$hint = "
			$hint<br />
				<div style=\"font-weight: normal; color: #aaa;\">$desc</div>
			</div>
			<div class=\"cnt-item\" style=\"overflow: hidden;\">
				<div>
					<div class=\"title\" style=\"font-weight: normal;\">
						$original: <div class=\"link samlib\" style=\"float: none;\">
							<a href=\"http://samlib.ru/$link1\">/$link1</a>
						</div>
						$comments: <div class=\"link samlib\" style=\"float: none;\">
							<a href=\"http://samlib.ru/comment/$link2\">/comment/$link2</a>
						</div>
						$diffs: <div class=\"link\" style=\"float: none;\">
							<a nofollow noindex href=\"?diff_mode=$ndiff\">$diffst</a><br >
							<a nofollow noindex href=\"?diff_always=$adiff\">$adiffs</a>
						</div>
					</div>
				</div>
				";

				View::addKey('hint', $hint);
		}

		function decodeVersion($r, $offset = 2, $diff = false) {
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

//			debug(array($t, $r, $date, $time));
			return $t;
		}

		function isDiffMode() {
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

		function fetchVersions($storage) {
			$p = array();
			$d = is_dir($storage) ? @dir($storage) : null;
			if ($d)
				while (($entry = $d->read()) !== false)
					if (is_file($storage . '/' . $entry) && ($version = intval(basename($entry, '.html'))))
						$p[] = $version;
			return $p;
		}

		function actionVersion($r) {
			$page = uri_frag($r, 0);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`, `author`, `description`, `link`');

			if (!$data['id'])
				throw new Exception('Page not found!');

			$aa = $this->getAggregator(1);
			$adata = $aa->get($author = intval($data['author']), '`fio`, `link`');
			$alink = "<a href=\"/authors/id/{$data['author']}\">{$adata['fio']}</a>";
			$plink = "<a href=\"/pages/version/{$page}\">{$data['title']}</a>";

			View::addKey('meta-keywords', "{$adata['fio']}, {$data['title']}");
			View::addKey('meta-description', Loc::lget('last_updates') . ": {$adata['fio']} - {$data['title']}. {$data['description']}");

			$storage = 'cms://cache/pages/' . $page;
			$p = $this->fetchVersions($storage);

			$ha = $this->getAggregator(3);
			$uid = $this->user->ID();
			$lastseen = uri_frag($_REQUEST, "ls_{$page}", -1);
			$trace    = -1;
			if ($uid) {
				$f = $ha->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` = $page limit 1", 'collumns' => '`lastseen`, `trace`'));
				if ($f['total']) {
					$lastseen = intval($f['data'][0]['lastseen']);
					$trace = intval($f['data'][0]['trace']);
				}
			}

			View::addKey('hint', $a->traceMark($uid, $trace, $page, $author));
			$action = post('action');
			if (!$action)
				$action = uri_frag($r, 1, null, false);

			View::addKey('title', "$alink - $plink");

			$cur_version = '';
			switch ($action) {
			case 'view':
				$version = $this->decodeVersion($r, 2);
				$cur_version = '&nbsp; <span style="font-size: 80%;">[' . Loc::lget('date') . ': ' . date('d.m.Y', $version) . ']</span>';
				$cnt = @file_get_contents("$storage/$version.html");
				if ($cnt !== false) {
					$cnt1 = @gzuncompress/**/($cnt);
					if ($cnt1 !== false)
						$cnt = $cnt1;
					else
						@file_put_contents("$storage/$version.html", gzcompress($cnt));
					$cnt1 = preg_replace('">([_\.]+)<"', '&gt;\1&lt;', $cnt1);
					$cnt = $this->prepareForGrammar($cnt, true);
				} else
					throw new Exception('Version not found!');

				View::addKey('preview', mb_convert_encoding($cnt, 'UTF-8', 'CP1251'));
//				View::addKey('grammar', $this->fetchGrammarSuggestions($page));
				View::addKey('h_old', '');
				View::addKey('h_new', '');
				$this->view->renderTPL('pages/view');
			default:
				rsort($p);
				$l = count($p) - 1;

				if ($l >= 0) {
					$newest = $p[0];
					$oldest = $p[count($p) - 1];
					$lastseen = ($lastseen >= 0) ? $lastseen : $newest;
					$diffopen = ($v = post_int('version')) ? $v : $lastseen;

					$p1 = '';
//					if ($this->isDiffMode()) {
						$row = array('root' => $this->_name, 'page' => $page);

						$ldate = date('d.m.Y', $newest);
						$full = Loc::lget('full_last_version');
						$last = $newest ? "<br /><span class=\"pull_right\">[$full: <a href=\"/pages/id/{$page}\">{$ldate}</a>]</span>" : '';
						View::addKey('moder', $last);
						$_ldiff = Loc::lget('diff');
						$_lview = Loc::lget('view');
						$_ldownload = Loc::lget('download');
						$_ldelete = Loc::lget('delete');
						foreach ($p as $idx => $version) {
							$row = array_merge($data, $row, $adata);
							$row['view'] = $_lview;
							$row['diff'] = $_ldiff;
							$row['download'] = $_ldownload;
							$row['delete'] = $_ldelete;
							$row['version'] = date('d-m-Y/H-i-s', $version);
							$row['prew'] = ($idx < $l) ? intval($p[$idx + 1]) : 0;
							$row['timestamp'] = date('d.m.Y H:i:s', $version);
							$row['size'] = fs(filesize("$storage/$version.html"));
							$t = '<a href="/{%root}/diff/{%page}/{%version}/{%prev}" {%oldest}>{%time}</a>';
							$u1 = array();
							foreach ($p as $v2)
								if ($v2 < $version) {
									$row['prev'] = date('d-m-Y/H-i-s', $v2);
									$row['time'] = date('d.m.Y', $v2);
									$fresh = ($v2 >= $diffopen) ? 'fresh' : 'new';
									$row['oldest'] = ($v2 >= $lastseen) ? " class=\"diff-to-{$fresh}\"" : '';
									$u1[strftime('%B, %Y', $v2)][] = patternize($t, $row);
								}
							$u = array();
							foreach ($u1 as $month => $versions) {
								$u[] = "<span class=\"nowrap\">$month</span><br />" . join('', $versions) . '<br />';
							}

							$row['prev'] = count($u) ? '<div class="versions"><div>' . join('', $u) . '</div></div>' : '';
							$row['last'] = !$idx ? 'last' : '';
							$row['moderated'] = $this->userModer ? patternize(self::VERSION_MODER, $row) : '';
							$p1 .= patternize(($idx != $l) ? self::VERSION_PATT : self::VERSION_PATT2, $row);
						}
//					} else
						if ($this->isDiffMode())
							echo $p1;
						else {
							$p2 = $this->buildCalendar($page, $p, $lastseen, $storage);
							echo "<div style='float: left; overflow: hidden;'>$p2</div><div style='float: left; overflow: hidden;'>$p1</div>";
						}
				} else {
					echo Loc::lget('pages_noversions');
					View::addKey('moder', '');
				}
			}
			$this->makeDetailHint($a->traceMark($uid, $trace, $page, $author) . $cur_version, $data['description'], $adata['link'], $data['link']);

			if ($action == 'view')
				$this->updateTrace($page, post_int('version'));
		}

		function buildCalendar($page, $versions, $lastseen, $storage) {
			$_tday = 60 * 60 * 24; // 1 day in seconds

			$result = '';
			$updates = explode(',', Loc::lget('updates'));
			$update_base = array_shift($updates);
			$read = Loc::lget('view');
			$row['diff'] = Loc::lget('diff');
			$download = Loc::lget('download');
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
				if (count($d) + $succ < 6 * 7) $succ += 7;


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
									$new = ($version > $lastseen) ? ' calendar-new' : '';
									$size = fs(@filesize("$storage/$version.html"));
									$time = date('H:i:s', $version);
									$version = date('d-m-Y/H-i-s', $version);
									$e[] = "<li class=\"$new\">&raquo; $time, $size: <a href=\"/pages/version/$page/view/$version\">$read</a> | <a href=\"/pages/download/$page/$version\">$download</a></li>";
								}
								$v = join('', $e);
								$m = ' calendar-multiple';
								$action = '';
								$dayname2 = date('j-m-Y', $day);
								$up_form = aaxx($cnt, $update_base, $updates);
								$dayname = "<a>$dayname</a><div class=\"calendar-multi-versions\"><b>$dayname2</b>: $cnt $up_form<br /><ul>$v</ul></div>";

							$current = " class=\"calendar-version{$m}{$new}\"";
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

		function actionDownload($r) {
			$page = uri_frag($r, 0);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$version = ($v = $this->decodeVersion($r, 1)) !== false ? $v : uri_frag($r, 1);
			if (!$version)
				throw new Exception('Unknown version!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`, `author`, `description`, `link`, `time`');

			if (!$data['id'])
				throw new Exception('Page not found!');

			$aa = $this->getAggregator(1);
			$adata = $aa->get($author = intval($data['author']), '`fio`, `link`');

			$storage = 'cms://cache/pages/' . $page;

			if (!is_file("$storage/$version.html"))
				throw new Exception('Cache not found!');

			$file = post('filename');

			$dld = $file != null;
			if ($dld) {
				$offset = ($v !== false) ? 1 : 0;
				$ftx = uri_frag($r, $offset + 2, 0, 0);
				$ftx = intval(strtolower($ftx) == 'txt');
				$enc = uri_frag($r, $offset + 3, 0, 0);
				$enc = intval(strtolower($enc) == 'utf-8');
				$arc = uri_frag($r, $offset + 4, 0, 0);
				$arc = intval(strtolower($arc) == 'zip');
			}

//			$enc = post_int('enc');
//			$ftx = post_int('fmt');
//			$dld = post('action') == 'download';

			$fio = $adata['fio'];
			$title = $data['title'];
			if (!$dld) {
				$alink = "<a href=\"/authors/id/{$data['author']}\">{$adata['fio']}</a>";
				$plink = "<a href=\"/pages/version/{$page}\">{$data['title']}</a>";

				View::addKey('meta-keywords', "{$adata['fio']}, {$data['title']}");
				View::addKey('meta-description', Loc::lget('last_updates') . ": {$adata['fio']} - {$data['title']}. {$data['description']}");
				$ha = $this->getAggregator(3);
				$uid = $this->user->ID();
				$trace    = -1;
				if ($uid) {
					$f = $ha->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` = $page limit 1", 'collumns' => '`trace`'));
					if ($f['total'])
						$trace = intval($f['data'][0]['trace']);
				}

				View::addKey('title', "$alink - $plink");
				View::addKey('version', date('d.m.Y H:i:s', $version));
				$this->makeDetailHint($a->traceMark($uid, $trace, $page, $author), $data['description'], $adata['link'], $data['link']);
			}

			$cnt = @file_get_contents("$storage/$version.html");
			$cnt1 = @gzuncompress/**/($cnt);
			if ($cnt1 !== false) $cnt = $cnt1; else @file_put_contents("$storage/$version.html", gzcompress($cnt));

/*			$cnt1 = preg_replace('">([_\.]+)<"', '&gt;\1&lt;', $cnt1);

			$cnt1 = trim(str_replace(array("\r", "\n"), '', $cnt1));
			$cnt1 = strip_tags($cnt1, '<strong><small><h1><h2><h3><h4><h5><h6><h7><h8><font><dd><p><div><span><a><ul><ol><li><img><table><tr><td><thead><tbody><br><u><b><i><s>');
			$cnt1 = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $cnt1);
			$cnt1 = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $cnt1);
			$cnt = str_replace(PHP_EOL, '<br />', $cnt1);*/

			$cnt = $this->prepareForGrammar($cnt1, true);

			require_once 'download.php';
			$filename = $this->genFilename($fio, $title);
			$html_title =  mb_convert_encoding("$fio - $title", 'CP1251', 'UTF-8');

			if ($dld) {
				switch (true) {
				case ($ftx): $_f = PDW::FMT_TXT; break;
				case (!$ftx): $_f = PDW::FMT_HTML; break;
				}

				switch (true) {
				case (!!$enc): $_e = PDW::ENC_UTF8; break;
				case (!$enc): $_e = PDW::ENC_WIN1251; break;
				}

				switch (true) {
				case (!!$arc): $_a = PDW::FILE_ARCH; break;
				case (!$arc): $_a = PDW::FILE_PLAIN; break;
				}

				$this->updateTrace($page, post_int('version'));
				$pdw = new PDW();
				$pdw->giveFile($html_title, $filename, $cnt, $_a | $_f | $_e, $version, true);
			}

			$version = date('d-m-Y/H-i-s', $version);
			View::addKey('options', PDW::enumFormats("/pages/download/$page/$version", $filename, $html_title, $cnt));
			$this->view->renderTPL('pages/download');
		}

		function genFilename($fio, $title) {
			$filename = preg_replace('/[^\p{L}\d-]/iu', '_', $fio) . '_-_' . preg_replace('/[^\p{L}\d-]/iu', '_', $title);
			$filename = preg_replace('/[_]{2,}/', '_', $filename);
			$filename = translit($filename);
			return str_replace('_.', '.', $filename);
		}

		function actionRemove($r) {
			$page = uri_frag($r, 0);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$timestamp = ($v = $this->decodeVersion($r, 1)) !== false ? $v : uri_frag($r, 1);
			if (!$timestamp)
				throw new Exception('Unknown version!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`, `author`, `description`, `link`, `size`, `time`');

			if (!$data['id'])
				throw new Exception('Page not found!');

			$aa = $this->getAggregator(1);
			$adata = $aa->get($author = intval($data['author']), '`fio`, `link`');

			$storage = 'cms://cache/pages/' . $page;
			$filename = "$storage/$timestamp.html";

			if (!is_file($filename))
				throw new Exception('Cache not found!');

			$p = $this->fetchVersions($storage);
			sort($p);
			$version = date("d/m/Y H:i:s", $timestamp);
			View::addKey('version', "<i>$version ($timestamp)</i>");
			View::addKey('size', fs(filesize($filename)));

			$fio = $adata['fio'];
			$title = $data['title'];
			if (!$dld) {
				$alink = "<a href=\"/authors/id/{$data['author']}\">{$adata['fio']}</a>";
				$plink = "<a href=\"/pages/version/{$page}\">{$data['title']}</a>";

				$ha = $this->getAggregator(3);
				$uid = $this->user->ID();
				$trace    = -1;
				if ($uid) {
					$f = $ha->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` = $page limit 1", 'collumns' => '`trace`'));
					if ($f['total'])
						$trace = intval($f['data'][0]['trace']);
				}

				View::addKey('title', "$alink - $plink");
				View::addKey('version', date('d.m.Y H:i:s', $timestamp));
				$this->makeDetailHint($a->traceMark($uid, $trace, $page, $author), $data['description'], $adata['link'], $data['link']);
			}

			$echoed = '';

			$idx = array_search($timestamp, $p);

			require_once 'core_updates.php';
			$f = array(UPKIND_ADDED, UPKIND_DELETED, UPKIND_SIZE);
			$f = join(',', $f);
			$ua = UpdatesAggregator::getInstance();
//			$ua->dbc->debug = 1;
			$d = $ua->fetch(array(
				'pagesize' => 0
			, 'nocalc' => 0
			, 'desc' => 0
			, 'filter' => "page = $page and `kind` in ($f) order by `time` limit 1"
			, 'collumns' => 'id'
			));
			$total = $d['total'];
			if ($total < ($vc = count($p))) {
				$p = array_slice($p, $vc - $total, $total);
				$idx = array_search($timestamp, $p);
				$echoed .= 'Warning: file versions are more than update records!<br />';
			}
//		debug(array($p, $timestamp, $idx));
			$d = $ua->fetch(array(
				'pagesize' => 0
			, 'nocalc' => 1
			, 'desc' => 0
			, 'filter' => "page = $page and `kind` in ($f) order by `time` limit $idx, 1"
			, 'collumns' => '*'
			));
			if ($d['total']) {
//				debug($d);
				$uid = intval($d['data'][0]['id']);
				$udelta = intval($d['data'][0]['value']);
				$sign = (($udelta >= 0) ? '+' : '') . $udelta;
				$echoed .= "Specified update has delta at $sign KB.<br />";
			} else
				$udelta = 0;

			if (post_int('force')) {
				if ($d['total']) {
//					debug($d);
					$uid = intval($d['data'][0]['id']);
					$udelta = intval($d['data'][0]['value']);
					$sign = ($udelta >= 0) ? '+' : '';
					$ua->dbc->update($ua->TBL_INSERT
					, "`value` = `value`{$sign}{$udelta}"
					, "`page` = '{$page}' and `kind` in ($f) and `id` > $uid"
					);
					$ua->delete($uid);
					$echoed .= 'Corresponding update record deleted, relative records modified.<br />';
				} else
					$udelta = 0;

				$last = ($c = count($p)) ? $p[$c - 1] : 0;
				// version to delete is latest in db -> move "latest" marker to previous version
				if ($last && ($last == $timestamp)) {
					// try to find latest update
					unlink("$storage/last.html");
					$latest = $c - 2;
					if ($latest >= 0) {
						$ts = $p[$latest];
						copy("$storage/$ts.html", "$storage/last.html");
					}
					$a->update(array('size' => intval($data['size']) - $udelta), $page);
					$echoed .= 'Last version shifted, page actual size modified.<br />';
				}

				// now, delete the version files
				View::renderMessage(Loc::lget(unlink($filename) ? 'msg_ok' : 'msg_err'), View::MSG_INFO);
			} else {
				$delete = ucfirst(Loc::lget('delete'));
				$a = array('delete' => $delete);
				View::addKey('action', patternize('<br /><br />&nbsp; <a href="?force=1">{%delete}</a>?', $a));
			}

			$this->view->renderTPL('pages/remove');
			echo '<br /><br />' . $echoed;
		}

		function updateTrace($page_id, $version) {
			if ($uid = $this->user->ID()) {
				$ha = $this->getAggregator(3);
				$s = $ha->dbc->select('history', "`user` = $uid and `page` = $page_id", '`id` as `0`');
				if ($s && mysql_numrows($s) && ($r = @mysql_result($s, 0)))
					$ha->upToDate(array(intval($r)), $version);
			} else {
				$t = time() + 2592000;
				preg_match('/(.*\.|^)([^\.]+\.[^\.]+)$/i', $_SERVER['HTTP_HOST'], $m);
				$m = '.' . $m[2];
				setcookie("ls_{$page_id}", max(post_int("ls_{$page_id}"), $version), $t, "/", $m);
			}
		}

		public function actionDiff($r) {
			$page = uri_frag($r, 0);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`, `author`');
			if ($data['id'] != $page)
				throw new Exception('Resource not found o__O');

			$u = $this->decodeVersion($r, 1, true);
			$cur = intval($u[0]);
			$old = intval($u[1]);

//			debug(array($cu, $old));

//			$u = explode(',', uri_frag($r, 1, '', 0));
//			$cur = intval($u[0]);
//			$old = intval($u[1]);

			if (!($cur * $old))
				throw new Exception('Old or current version not found');

			$storage = SUB_DOMEN . "/cache/pages/$page";


			$d = array(false => '=&gt;', true => '&lt;=');
			$show_old = post('showold') == 'true';
			$aa = $this->getAggregator(1);
			$adata = $aa->get(intval($data['author']), '`fio`');
			$alink = "<a href=\"/authors/id/{$data['author']}\">{$adata['fio']}</a>";
			$plink = "<a href=\"/pages/version/{$page}\">{$data['title']}</a>";
			$old_d = date('d.m.Y', $old);
			$cur_d = date('d.m.Y', $cur);
			$diff_ver = date('d-m-Y/H-i-s', $cur) . '/' . date('d-m-Y/H-i-s', $old);
//			$diff_ver = $cur . ',' . $old;
			View::addKey('title', $alink . ' - ' . $plink . " <span style=\"font-size: 80%;\">[$old_d <a href=\"/pages/diff/$page/$diff_ver" . ($show_old ? '' : '?showold=true') . "\">" . $d[$show_old] . "</a> $cur_d]</span>");

			$t1 = @file_get_contents("$storage/{$old}.html");
			$t2 = @file_get_contents("$storage/{$cur}.html");
			$_t1 = @gzuncompress/**/($t1);
			if ($_t1 !== false) $t1 = $_t1; else @file_put_contents("$storage/{$old}.html", gzcompress($t1));
			$_t2 = @gzuncompress/**/($t2);
			if ($_t2 !== false) $t2 = $_t2; else @file_put_contents("$storage/{$cur}.html", gzcompress($t2));

			$t1 = $this->prepareForGrammar($t1, true);
			$t2 = $this->prepareForGrammar($t2, true);
//			$t1 = mb_convert_encoding($t1, 'UTF8', 'cp1251');
//			$t2 = mb_convert_encoding($t2, 'UTF8', 'cp1251');
//			debug(array($t1, $t2));

//			@ob_end_flush();
//			@ob_end_flush();
			require_once 'core_diff.php';
			ob_start();
			$io = new DiffIO(1024);
			$io->show_new = !$show_old;
			$db = new DiffBuilder($io);
			$h = $db->diff($t1, $t2);
			$c = ob_get_contents();
			ob_end_clean();

//			$c = $this->prepareForGrammar($c, true);

			$old = count($h[0]) ? str_replace(array(PHP_EOL, '\n'), '<br />', join('", "', $h[0])) : '';
			$new = count($h[1]) ? str_replace(array(PHP_EOL, '\n'), '<br />', join('", "', $h[1])) : '';
//			View::addKey('grammar', $this->fetchGrammarSuggestions($page));
			View::addKey('preview', $c);
			View::addKey('h_old', $old);
			View::addKey('h_new', $new);
			$this->view->renderTPL('pages/view');

			$this->updateTrace($page, $cur);
		}

		function prepareForGrammar($c, $cleanup = false) {
			if ($cleanup) {
				$c = strip_tags($c, '<strong><small><h1><h2><h3><h4><h5><h6><h7><h8><font><dd><p><div><span><a><ul><ol><li><img><table><tr><td><thead><tbody><br><u><b><i><s>');
				$c = preg_replace('"<p([^>]*)?>(.*?)<dd>"i', '<p\1>\2<dd>', $c);
				$c = preg_replace('"(</?(td|tr|table)[^>]*>)'.PHP_EOL.'"', '\1', $c);
				$c = preg_replace('"'.PHP_EOL.'(</?(td|tr|table)[^>]*>)"', '\1', $c);
				$c = str_replace(array("\r", "\n"), '', $c);
				$c = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $c);
				$c = preg_replace('"<p\s*>([^<]*)</p>"i', '<p/>\1', $c);
				$c = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $c);
				$c = preg_replace('"<(i|s|u)>(\s*)</\1>"', '\2', $c);
				$c = preg_replace('"</(i|s|u)>((\s|\&nbsp;)*)?<\1>"i', '\2', $c);
				$c = preg_replace('"<(font|span)\s*(lang=\"?[^\"]+\"?)\s*>([^<]*)</\1>"i', '\3', $c);
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
			return /*$cleanup ? $c : */str_replace(PHP_EOL, PHP_EOL . '<br />', $c);
		}

		function fetchGrammarSuggestions($page, $approved = 1) {
			$zone = str_replace("/{$this->_name}/", '', $_SERVER['REQUEST_URI']);
			$ga = $this->getAggregator(4);
			$d = $ga->fetch(array('nocalc' => true, 'desc' => 0
			, 'filter' => "`page` = $page and `zone` = '$zone'"// and `approved`"
			, 'collumns' => '`id`, `user`, `range`, `replacement`'
			));
			if ($d['total']) {
				$r = array();
				foreach ($d['data'] as &$row)
					$r[$row['range']][] = $row;

				foreach ($r as $range => $data) {
					$j = array();
					foreach ($data as $row) {
						$row['replacement'] = str_replace(array("\n", "\r", '"'), array('<br />\\n', '', '&quot;'), $row['replacement']);
						$j[] = patternize('{"i": {%id}, "u": {%user}, "r": "{%replacement}"}', $row);
					}
					$r[$range] = '{"range": "' . $range . '", "suggestions": [' . join(',', $j) . ']}';
				}
				$grammar = join(',', $r);
				return $grammar;
			}
			return '';
		}

		function actionCleanup($r) {
			$save = uri_frag($r, 0, 3);
			$save = max(2, $save);
			$force = post_int('force');
			$dir = 'cms://cache/pages';
			$d = is_dir($dir) ? @dir($dir) : null;
			$p = array();
			if ($d)
				while (($entry = $d->read()) !== false)
					if (($entry != '.') && ($entry != '..'))
						if (intval($entry) && is_dir("$dir/$entry"))
							$p[] = $entry;

			$msg = '';
			$s = 0;
			$pa = $this->getAggregator();
			$aa = $this->getAggregator(1);
			foreach ($p as $page_id) {
				$cache = "$dir/$page_id";
				$d = @dir($cache);
				$c = array();
				$o = 0;
				$v = 0;
				if ($d)
					while (($entry = $d->read()) !== false)
						if (intval($entry) && is_file("$cache/$entry")) {
							$c[$v++] = intval($entry);
							$o += filesize("$cache/$entry");
						}

				$g = $pa->get($page_id, '`title`, `author`');
				$a = $aa->get(intval($g['author']), '`fio`');

				if (count($c) > $save) {
					rsort($c);
					$c = array_slice($c, $save);
					$t = 0;
					foreach ($c as $version) {
						$t += @filesize("$cache/$version.html");
						if ($force)
							@unlink("$cache/$version.html");
					}
					$s += $t;
					$data = array(
						'id' => $page_id
					, 'author:id' => $g['author']
					, 'author' => $a['fio']
					, 'title' => $g['title']
					, 'size' => fs($o)
					, 'versions' => $v
					, 'delete' => $v - $save
					, 'free' => fs($t)
					);
					$msg .= patternize(Loc::lget('cleanup'), $data);
				}
			}
			$data = array('delete' => fs($s));
			$req = patternize($force ? Loc::lget('deleted') : Loc::lget('do_delete'), $data);
			echo $msg . '<br />' . $req;
			$this->view->renderMessage($req, View::MSG_INFO);

			$o = msqlDB::o();
			$o->query('drop table if exists `temp`');
			$o->query('create table `temp` (id int null)');
			$o->query('insert into `temp` (SELECT g.id FROM `groups` g left join `pages` p on g.id = p.`group` where p.id is null)');
			$o->query('delete from `groups` where id in (select * from `temp`)');
			$o->query('drop table `temp`');
		}

	}

?>