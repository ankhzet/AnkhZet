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
							<span class="head"><a href="/authors/id/{%author}">{%fio}</a> - <a href="/{%root}/id/{%id}">{%title}</a>{%moder}</span>
							<span class="link samlib"><a href="http://samlib.ru/{%autolink}/{%link}">{%autolink}/{%link}</a></span>
							<span class="link size">{%size}KB</span>
						</div>
						<div class="text">
							{%description}
						</div>
						<div class="text">
							<a href="/{%root}/version/{%id}">{%versions}</a> | <a href="/updates/trace/{%author}/{%id}">{%trace}</a>{%group}
						</div>
					</div>
';
		const VERSION_PATT = '
		<div class="cnt-item">
			<div class="title">
				<span class="head">{%timestamp}</span>
				<span class="link size">{%size}</span>
			</div>
			<div class="text">[ <a href="/{%root}/version/{%page}?action=view&version={%version}">{%view}</a><span class="v-diff {%last}">&nbsp;| {%diff}: {%prev}</span> ]</div>
		</div>
		';
		const VERSION_PATT2 = '
		<div class="cnt-item">
			<div class="title">
				<span class="head">{%timestamp}</span>
				<span class="link size">{%size}</span>
			</div>
			<div class="text">[
				<a href="/{%root}/version/{%page}?action=view&version={%version}">{%view}</a>
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
			if (!$author && $group) {
				$ga = $this->getAggregator(2);
				$g = $ga->get($group, '`author`, `title`');
				$this->group_title = $g['title'];
				$author = intval($g['author']);
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
				$this->groups[$group] = $g;

			}

			$row['group'] = $group ? patternize(Loc::lget('group_patt'), $this->groups[$group]) : '';
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			$g = $this->getAggregator(1);
			$a = $g->get($row['author'], '`fio`, `link`');
			$this->author_fio = $a['fio'] ? $a['fio'] : '&lt;author&gt;';
			$this->author_link = $a['link'];
			$page = intval($row['id']);
			$version = intval($row['utime']);

			View::addKey('title', "<a href=\"/authors/id/{$row['author']}\">{$this->author_fio}</a> - <a href=\"/pages/version/{$page}\">{$row['title']}</a>");
			html_escape($row, array('author', 'link'));
			$row['date'] = Loc::lget('date');
			$row['original'] = Loc::lget('original');
			$row['fio'] = $this->author_fio;
			$row['autolink'] = $this->author_link;
			$row['content'] = @file_get_contents(SUB_DOMEN . '/cache/pages/' . $row['id'] . '/last.html');
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

		function actionVersion($r) {
			$page = uri_frag($r, 0);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`, `author`');

			if (!$data['id'])
				throw new Exception('Page not found!');

			$aa = $this->getAggregator(1);
			$adata = $aa->get(intval($data['author']), '`fio`');
			$alink = "<a href=\"/authors/id/{$data['author']}\">{$adata['fio']}</a>";
			$plink = "<a href=\"/pages/version/{$page}\">{$data['title']}</a>";

			$storage = SUB_DOMEN . '/cache/pages/' . $page;
			$p = array();
			$d = @dir($storage);
			if ($d)
				while (($entry = $d->read()) !== false)
					if (is_file($storage . '/' . $entry) && ($version = intval(basename($entry, '.html'))))
						$p[] = $version;

			$ha = $this->getAggregator(3);
			$uid = $this->user->ID();
			switch ($action = post('action')) {
			case 'view':
				View::addKey('title', $alink . ' - ' . $plink);
				View::addKey('moder', '<span style="font-size: 80%;">[update: ' . date('d.m.Y', $version) . ']</span>');
				$version = post_int('version');
				$cnt = @file_get_contents("$storage/$version.html");
				$cnt1 = @gzuncompress/**/($cnt);
				if ($cnt1 !== false) $cnt = $cnt1;
				$cnt = $this->prepareForGrammar($cnt, true);
				View::addKey('preview', mb_convert_encoding($cnt, 'UTF-8', 'CP1251'));
				View::addKey('grammar', $this->fetchGrammarSuggestions($page));
				View::addKey('h_old', '');
				View::addKey('h_new', '');
				$this->view->renderTPL('pages/view');
			default:
				rsort($p);
				$l = count($p) - 1;
				$row = array('root' => $this->_name, 'page' => $page);
				$newest = $p[0];
				$oldest = $p[count($p) - 1];

				if ($uid) $f = $ha->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` = $page limit 1", 'collumns' => '`time`'));
				$lastseen = ($uid && $f['total']) ? intval($f['data'][0]['time']) : post_int("ls_{$page}");
				$lastseen = $lastseen ? $lastseen : $newest;

				$diffopen = ($v = post_int('version')) ? $v : $lastseen;

				$ldate = date('d.m.Y', $newest);
				$full = Loc::lget('full_last_version');
				$last = $newest ? "<span class=\"pull_right\">[$full: <a href=\"/pages/id/{$page}\">{$ldate}</a>]</span>" : '';
				View::addKey('title', "$alink - $plink");
				View::addKey('moder', $last);
				if ($l >= 0)
					foreach ($p as $idx => $version) {
						$row = array_merge($data, $row, $adata);
						$row['view'] = Loc::lget('view');
						$row['diff'] = Loc::lget('diff');
						$row['version'] = $version;
						$row['prew'] = ($idx < $l) ? intval($p[$idx + 1]) : 0;
						$row['timestamp'] = date('d.m.Y h:i:s', $version);
						$row['size'] = fs(filesize("$storage/$version.html"));
						$t = '<a href="/{%root}/diff/{%page}/{%version},{%prev}" {%oldest}>{%time}</a>';
						$u = array();
						foreach ($p as $v2)
							if ($v2 < $version) {
								$row['prev'] = $v2;
								$row['time'] = date('d.m.Y', $v2);
								$fresh = ($v2 >= $diffopen) ? 'fresh' : 'new';
								$row['oldest'] = ($v2 >= $lastseen) ? " class=\"diff-to-{$fresh}\"" : '';
								$u[] = patternize($t, $row);
							}

						$row['prev'] = count($u) ? '&rarr; <div class="versions"><div>' . join('', $u) . '</div></div>' : '';
						$row['last'] = !$idx ? 'last' : '';
						echo patternize(($idx != $l) ? self::VERSION_PATT : self::VERSION_PATT2, $row);
					}
				else
					echo Loc::lget('pages_noversions');
			}

			if ($action == 'view')
				$this->updateTrace($page, post_int('version'));
		}

		function updateTrace($page_id, $version) {
			if ($uid = $this->user->ID()) {
				$ha = $this->getAggregator(3);
				$s = $ha->dbc->select('history', "`user` = $uid and `page` = $page_id", '`id` as `0`');
				if ($s && ($r = @mysql_result($s, 0)))
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

			$u = explode(',', uri_frag($r, 1, '', 0));
			$cur = intval($u[0]);
			$old = intval($u[1]);

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
			View::addKey('title', $alink . ' - ' . $plink . " <span style=\"font-size: 80%;\">[$old_d <a href=\"" . ($show_old ? $cur . ',' . $old : '?showold=true') . "\">" . $d[$show_old] . "</a> $cur_d]</span>");

			$t1 = @file_get_contents("$storage/{$old}.html");
			$t2 = @file_get_contents("$storage/{$cur}.html");
			$_t1 = @gzuncompress/**/($t1); if ($_t1 !== false) $t1 = $_t1;
			$_t2 = @gzuncompress/**/($t2); if ($_t2 !== false) $t2 = $_t2;


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
//			$t2 = preg_replace('"</([^>]+)>((<br\s*/>|\s|\&nbsp;)*)?<\1>"i', '\2', $t2);
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

			$old = count($h[0]) ? str_replace(PHP_EOL, '<br />', join('", "', $h[0])) : '';
			$new = count($h[1]) ? str_replace(PHP_EOL, '<br />', join('", "', $h[1])) : '';
			View::addKey('grammar', $this->fetchGrammarSuggestions($page));
			View::addKey('preview', $c);
			View::addKey('h_old', $old);
			View::addKey('h_new', $new);
			$this->view->renderTPL('pages/view');

			$this->updateTrace($page, $cur);
		}

		function prepareForGrammar($c, $cleanup = false) {
			if ($cleanup) {
				$c = strip_tags($c, '<dd><p><br><u><b><i><s>');
				$c = preg_replace('"<p([^>]*)?>(.*?)<dd>"i', '<p\1>\2</p><dd>', $c);
				$c = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $c);
				$c = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $c);
			} else
				$c = str_replace('<br />', PHP_EOL, $c);

			$c = preg_replace('"<([^>]+)>(\s*)</\1>"', '\2', $c);
			$c = preg_replace('"</([^>]+)>((\s|\&nbsp;)*)?<\1>"i', '\2', $c);
			$idx = 0;
			$p = 0;
			while (preg_match('"<(([\w]+)([^>/]*))>"', substr($c, $p), $m, PREG_OFFSET_CAPTURE)) {
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
			$dir = SUB_DOMEN . '/cache/pages';
			$d = @dir($dir);
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
			View::renderMessage($req, View::MSG_INFO);
		}

	}

?>