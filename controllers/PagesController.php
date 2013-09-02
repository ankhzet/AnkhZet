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
			]</div>
		</div>
		';

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

			View::addKey('grammar', $this->fetchGrammarSuggestions(intval($row['id'])));
			View::addKey('preview', $row['content']);
			View::addKey('h_old', '');
			View::addKey('h_new', '');
			$this->view->renderTPL('pages/view');
			$this->updateTrace($page, $version);
			return '';//patternize($this->ID_PATTERN, $row);
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
			$p = array();
			$d = is_dir($storage) ? @dir($storage) : null;
			if ($d)
				while (($entry = $d->read()) !== false)
					if (is_file($storage . '/' . $entry) && ($version = intval(basename($entry, '.html'))))
						$p[] = $version;

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
				View::addKey('grammar', $this->fetchGrammarSuggestions($page));
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
					$row = array('root' => $this->_name, 'page' => $page);

					$ldate = date('d.m.Y', $newest);
					$full = Loc::lget('full_last_version');
					$last = $newest ? "<br /><span class=\"pull_right\">[$full: <a href=\"/pages/id/{$page}\">{$ldate}</a>]</span>" : '';
					View::addKey('moder', $last);
					foreach ($p as $idx => $version) {
						$row = array_merge($data, $row, $adata);
						$row['view'] = Loc::lget('view');
						$row['diff'] = Loc::lget('diff');
						$row['download'] = Loc::lget('download');
						$row['version'] = date('d-m-Y/H-i-s', $version);
						$row['prew'] = ($idx < $l) ? intval($p[$idx + 1]) : 0;
						$row['timestamp'] = date('d.m.Y H:i:s', $version);
						$row['size'] = fs(filesize("$storage/$version.html"));
						$t = '<a href="/{%root}/diff/{%page}/{%version}/{%prev}" {%oldest}>{%time}</a>';
						$u = array();
						foreach ($p as $v2)
							if ($v2 < $version) {
								$row['prev'] = date('d-m-Y/H-i-s', $v2);
								$row['time'] = date('d.m.Y', $v2);
								$fresh = ($v2 >= $diffopen) ? 'fresh' : 'new';
								$row['oldest'] = ($v2 >= $lastseen) ? " class=\"diff-to-{$fresh}\"" : '';
								$u[] = patternize($t, $row);
							}

						$row['prev'] = count($u) ? '&rarr; <div class="versions"><div>' . join('', $u) . '</div></div>' : '';
						$row['last'] = !$idx ? 'last' : '';
						echo patternize(($idx != $l) ? self::VERSION_PATT : self::VERSION_PATT2, $row);
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
			$cnt1 = preg_replace('">([_\.]+)<"', '&gt;\1&lt;', $cnt1);

			$cnt1 = trim(str_replace(array("\r", "\n"), '', $cnt1));
			$cnt1 = strip_tags($cnt1, '<strong><small><h1><h2><h3><h4><h5><h6><h7><h8><font><dd><p><div><span><a><ul><ol><li><img><table><tr><td><thead><tbody><br><u><b><i><s>');
			$cnt1 = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $cnt1);
			$cnt1 = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $cnt1);
			$cnt = str_replace(PHP_EOL, '<br />', $cnt1);

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

		function makeDetailHint($hint, $desc, $alink, $link) {
				$original = Loc::lget('original');
				$comments = Loc::lget('comments');
				$link1 = "$alink/$link";
				$link2 = str_replace('.shtml', '', $link1);
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
				</div>
				</div>
				";

				View::addKey('hint', $hint);
		}

		function genFilename($fio, $title) {
			$filename = preg_replace('/[^\p{L}\d-]/iu', '_', $fio) . '_-_' . preg_replace('/[^\p{L}\d-]/iu', '_', $title);
			$filename = preg_replace('/[_]{2,}/', '_', $filename);
			$filename = translit($filename);
			return str_replace('_.', '.', $filename);
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

			/*
			 >_< >.<
			 */
			$t1 = preg_replace('">([_\.]+)<"', '&gt;\1&lt;', $t1);
			$t2 = preg_replace('">([_\.]+)<"', '&gt;\1&lt;', $t2);

			$t1 = trim(str_replace(array("\r", "\n"), '', $t1));
			$t1 = strip_tags($t1, '<dd><p><br><u><i><s>');
			$t1 = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $t1);
			$t1 = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $t1);

			$t2 = trim(str_replace(array("\r", "\n"), '', $t2));
			$t2 = strip_tags($t2, '<dd><p><br><u><i><s>');
			$t2 = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $t2);
			$t2 = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $t2);

//			$t1 = preg_replace('"<([^>]+)>((<br\s*/>|\s)*)</\1>"', '\2', $t1);
//			$t1 = preg_replace('"</([^>]+)>((<br\s*/>|\s|\&nbsp;)*)?<\1>"i', '\2', $t1);
//			$t2 = preg_replace('"<([^>]+)>((<br\s*/>|\s)*)</\1>"', '\2', $t2);
//			t2 = preg_replace('"</([^>]+)>((<br\s*/>|\s|\&nbsp;)*)?<\1>"i', '\2', $t2);
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

			$c = $this->prepareForGrammar($c);

			$old = count($h[0]) ? str_replace(array(PHP_EOL, '\n'), '<br />', join('", "', $h[0])) : '';
			$new = count($h[1]) ? str_replace(array(PHP_EOL, '\n'), '<br />', join('", "', $h[1])) : '';
			View::addKey('grammar', $this->fetchGrammarSuggestions($page));
			View::addKey('preview', $c);
			View::addKey('h_old', $old);
			View::addKey('h_new', $new);
			$this->view->renderTPL('pages/view');

			$this->updateTrace($page, $cur);
		}

		function prepareForGrammar($c, $cleanup = false) {
			if ($cleanup) {
				$c = strip_tags($c, '<dd><p><div><span><a><ul><ol><li><img><table><tr><td><thead><tbody><br><u><b><i><s>');
				$c = preg_replace('"<p([^>]*)?>(.*?)<dd>"i', '<p\1>\2</p><dd>', $c);
				$c = preg_replace('"(</?(td|tr|table)[^>]*>)'.PHP_EOL.'"', '\1', $c);
				$c = preg_replace('"'.PHP_EOL.'(</?(td|tr|table)[^>]*>)"', '\1', $c);
				$c = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $c);
				$c = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $c);
				$c = preg_replace('"<(i|s|u)>(\s*)</\1>"', '\2', $c);
				$c = preg_replace('"</(i|s|u)>((\s|\&nbsp;)*)?<\1>"i', '\2', $c);

			} else
				$c = str_replace('<br />', PHP_EOL, $c);

			$idx = 0;
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
			}
			$p = 0;
			while (preg_match('|<img [^>]*(src=(["\']?))/[^\2>]+\2[^>]*(>)|i', substr($c, $p), $m, PREG_OFFSET_CAPTURE)) {
				$p += intval($m[1][1]);
				$u = $m[1][0] . 'http://samlib.ru';
				$c = substr_replace($c, $u, $p, strlen($m[1][0]));
				$p += strlen($u) + intval($m[3][1]) - intval($m[1][1]) - 5;
			}
			return str_replace(PHP_EOL, '<br />', $c);
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