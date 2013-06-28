<?php
	require_once 'core_page.php';
	require_once 'core_authors.php';
	require_once 'core_history.php';
	require_once 'AggregatorController.php';

	class PagesController extends AggregatorController {
		protected $_name = 'pages';

		var $USE_UL_WRAPPER = false;
		var $MODER_EDIT = '<span class="pull_right">[<a href="/{%root}/delete/{%id}">{%delete}</a>]</span>';
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
							<a href="/{%root}/version/{%id}">{%versions}</a>{%group}
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
				<a href="/{%root}/version/{%page}?action=view&version={%version}">{%view}</a> |
				<a href="/{%root}/diff/{%page}/{%version},{%prew}"{%last}>{%diff}</a> &darr;
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

		public function action($r) {
			$author = intval($_REQUEST['author']);
			$group = intval($_REQUEST['group']);
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

			parent::action($r);
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

			View::addKey('title', '<a href="/authors/id/' . $row['author'] . '">' . $this->author_fio . '</a> - ' . $row['title']);
			html_escape($row, array('author', 'link'));
			$row['date'] = Loc::lget('date');
			$row['original'] = Loc::lget('original');
			$row['fio'] = $this->author_fio;
			$row['autolink'] = $this->author_link;
			$row['content'] = @file_get_contents(SUB_DOMEN . '/cache/pages/' . $row['id'] . '/last.html');
			if ($row['content'] !== false) {
				$len = strlen($row['content']);
				$c = @gzuncompress/**/($row['content']);
				if ($c !== false) $row['content'] = $c;
				$row['content'] = mb_convert_encoding($row['content'], 'UTF-8', 'CP1251');
			}
			$len = strlen($row['content']);
			return patternize($this->ID_PATTERN, $row);
		}

		function actionCleanup($r) {
			$save = intval($r[0]) ? intval($r[0]) : 3;
			$save = max(2, $save);
			$force = intval($_REQUEST['force']);
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

		function actionVersion($r) {
			$page = intval($r[0]);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`, `author`');

			if (!$data['id'])
				throw new Exception('Page not found!');

			$aa = $this->getAggregator(1);
			$adata = $aa->get(intval($data['author']), '`fio`');

			$storage = SUB_DOMEN . '/cache/pages/' . $page;
			$p = array();
			$d = @dir($storage);
			if ($d)
				while (($entry = $d->read()) !== false)
					if (is_file($storage . '/' . $entry) && ($version = intval(basename($entry, '.html'))))
						$p[] = $version;

			$alink = "<a href=\"/authors/id/{$data['author']}\">{$adata['fio']}</a>";
			$plink = "<a href=\"/pages/id/{$page}\">{$data['title']}</a>";

			switch ($_REQUEST['action']) {
			case 'view':
				View::addKey('title', $alink . ' - ' . $plink . ' <span style="font-size: 80%;">[update: ' . date('d.m.Y', $version) . ']</span>');
				$version = intval($_REQUEST['version']);
				$cnt = @file_get_contents("$storage/$version.html");
				$cnt1 = @gzuncompress/**/($cnt);
				if ($cnt1 !== false) $cnt = $cnt1;
				$cnt = mb_convert_encoding($cnt, 'UTF-8', 'CP1251');
				echo "<div class=\"cnt-item\"><div class=\"text reader\">$cnt</div></div>";
			default:
				View::addKey('title', $alink . ' - ' . $plink);
				rsort($p);
				$l = count($p) - 1;
				$row = array('root' => $this->_name, 'page' => $page);
				foreach ($p as $idx => $version) {
					$row = array_merge($data, $row, $adata);
					$row['view'] = Loc::lget('view');
					$row['diff'] = Loc::lget('diff');
					$row['version'] = $version;
					$row['prew'] = intval($p[$idx + 1]);
					$row['timestamp'] = date('d.m.Y h:i:s', $version);
					$row['size'] = fs(filesize("$storage/$version.html"));
					echo patternize(($idx != $l) ? self::VERSION_PATT : self::VERSION_PATT2, $row);
				}
			}
			$uid = $this->user->ID();
			if ($uid) {
				$ha = $this->getAggregator(3);
				$s = $a->dbc->select('history', '`user` = ' . $uid . ' and `page` = ' . $page, '`id` as `0`');
				if ($s && ($r = @mysql_result($s, 0)))
					$ha->upToDate(array(intval($r)));
			}
		}

		public function actionDiff($r) {
			$page = intval($r[0]);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`');
			if ($data['id'] != $page)
				throw new Exception('Resource not found o__O');

			$r = explode(',', $r[1]);
			$cur= intval($r[0]);
			$old= intval($r[1]);

			if (!($cur * $old))
				throw new Exception('Old or current version not found');

			$storage = SUB_DOMEN . '/cache/pages/' . $page;

			$cur .= '.html';
			$old .= '.html';

			$d = array(false => '=&gt;', true => '&lt;=');
			$show_old = $_REQUEST['showold'] == 'true';
			echo 'Resource: <a href="/pages/version/' . $page . '">' . $data['title'] . '</a><br />';
			echo "[$old <a href=\"?showold=" . ($show_old ? 'false' : 'true') . "\">" . $d[$show_old] . "</a> $cur]<hr />";
			$t1 = @file_get_contents($storage . '/' . $old);
			$t2 = @file_get_contents($storage . '/' . $cur);
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
			define('PHP_EOL', "\n");
			ob_start();
//			echo '<pre>';
			$io = new DiffIO(1024);
			$io->show_new = !$show_old;
			$db = new DiffBuilder($io);
			$h = $db->diff($t1, $t2);//, intval($_REQUEST['notdeep']) ? $db->DIFF_TEXT_SPLITTERS2 : $db->DIFF_TEXT_SPLITTERS);
			$c = ob_get_contents();
			ob_end_clean();
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
					$r = "<$tag node=\"$idx\"$attr>";
					$c = substr_replace($c, $r, $p, strlen($sub));
//					debug(array($tag, $p, $attr));
					$p += strlen($r);
				} else
					$p += strlen($sub);
//				break;
			}

			$ga = $this->getAggregator(4);
			$d = $ga->fetch(array('nocalc' => true, 'desc' => 0
			, 'filter' => "`page` = $page"// and `approved`"
			, 'collumns' => '`id`, `user`, `range`, `replacement`'
			));
			if ($d['total']) {
				$r = array();
				foreach ($d['data'] as &$row)
					$r[$row['range']][] = $row;

				foreach ($r as $range => $data) {
					$j = array();
					foreach ($data as $row) {
						html_escape($row, array('replacement'));
						$j[] = patternize('{"i", {%id}, "u": {%user}, "r": "{%replacement}" />', $row);
					}
					$r[$range] = '{"range": "' . $range . '", "suggestions": [' . join(',', $j) . ']}';
				}
				$grammar = join(',', $r);
				echo "<script>var grammar = [$grammar]</script>";
			}

			$c = str_replace(PHP_EOL, '<br />', $c);
			echo $c;
			$old = str_replace(PHP_EOL, '<br />', join('", "', $h[0]));
			$new = str_replace(PHP_EOL, '<br />', join('", "', $h[1]));
			echo "<script> var text_old = [\"{$old}\"], text_new = [\"{$new}\"];</script>\n";
		}
	}

?>