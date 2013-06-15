<?php
	require_once 'core_page.php';
	require_once 'core_authors.php';
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
							<span class="head">{%fio} - <a href="/{%root}/id/{%id}">{%title}</a>{%moder}</span>
							<span class="link samlib"><a href="http://samlib.ru/{%autolink}/{%link}">{%autolink}/{%link}</a></span>
							<span class="link size">{%size}KB</span>
						</div>
						<div class="text">
							{%description}
						</div>
						<div class="text">
							<a href="/{%root}/version/{%id}">{%versions}</a>
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
				<span class="head">{%timestamp} - <a href="/{%root}/id/{%id}">{%title}</a></span>
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
			}
		}

		public function action($r) {
			$author = intval($_REQUEST[author]);
			if ($author) {
				$this->query = '`author` = ' . $author;
				$this->link = '?author=' . $author;
				$g = $this->getAggregator(1);
				$a = $g->get($author);
				$this->author_fio = $a['fio'] ? $a['fio'] : '&lt;author&gt;';
				$this->author_link = $a['link'];
			}
			parent::action($r);
		}

		public function makeItem(&$aggregator, &$row) {
			html_escape($row, array('link'));

			$row['versions'] = Loc::lget('versions');
			$row['fio'] = $this->author_fio;
			$row['autolink'] = $this->author_link;
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
			if ($row['content']) {
				$c = @file_get_contents(SUB_DOMEN . '/cache/pages/' . $row['id'] . '/last.html');
				$len = strlen($row['content']);
				$row['content'] = @gzuncompress/**/($c);
				$row['content'] = mb_convert_encoding($row['content'], 'UTF-8', 'CP1251');
			}
			header('Content-Length: ' . $len);
			return patternize($this->ID_PATTERN, $row);
		}


		function actionVersion($r) {
			$page = intval($r[0]);
			if (!$page)
				throw new Exception('Page ID not specified!');

			$a = $this->getAggregator(0);
			$data = $a->get($page, '`id`, `title`');

			$storage = SUB_DOMEN . '/cache/pages/' . $page;
			$p = array();
			$d = @dir($storage);
			if ($d)
				while (($entry = $d->read()) !== false)
					if (is_file($storage . '/' . $entry) && ($version = intval(basename($entry, '.html'))))
						$p[] = $version;

			switch ($_REQUEST['action']) {
			case 'view':
				View::addKey('title', $data['title'] . ' <span style="font-size: 80%;">[update: ' . date('d.m.Y', $version) . ']</span>');
				$version = intval($_REQUEST['version']);
				$cnt = @file_get_contents("$storage/$version.html");
				$cnt1 = @gzuncompress/**/($cnt);
				if ($cnt1 !== false) $cnt = $cnt1;
				$cnt = mb_convert_encoding($cnt, 'UTF-8', 'CP1251');
				echo $cnt;
			default:
				View::addKey('title', $data['title']);
				rsort($p);
				$l = count($p) - 1;
				$row = array('root' => $this->_name, 'page' => $page);
				foreach ($p as $idx => $version) {
					$row = array_merge($data, $row);
					$row['view'] = Loc::lget('view');
					$row['diff'] = Loc::lget('diff');
					$row['version'] = $version;
					$row['prew'] = intval($p[$idx + 1]);
					$row['timestamp'] = date('d.m.Y h:i:s', $version);
					$row['size'] = fs(filesize("$storage/$version.html"));
					echo patternize(($idx != $l) ? self::VERSION_PATT : self::VERSION_PATT2, $row);
				}
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
			$show_old = $_REQUEST[showold] == 'true';
			echo 'Resource: <a href="/pages/id/' . $page . '">' . $data['title'] . '</a><br />';
			echo "[$old <a href=\"?showold=" . ($show_old ? 'false' : 'true') . "\">" . $d[$show_old] . "</a> $cur]<hr />";
			$t1 = @file_get_contents($storage . '/' . $old);
			$t2 = @file_get_contents($storage . '/' . $cur);
			$_t1 = @gzuncompress/**/($t1);
			if ($_t1 !== false) $t1 = $_t1;
			$_t2 = @gzuncompress/**/($t2);
			if ($_t2 !== false) $t2 = $_t2;
//			$t1 = mb_convert_encoding($t1, 'UTF-8', 'CP1251');
//			$t2 = mb_convert_encoding($t2, 'UTF-8', 'CP1251');

			require_once 'core_diff.php';
//			ob_start();
//			echo '<pre>';
			$io = new DiffIO();
			$io->show_new = !$show_old;
			$db = new DiffBuilder($io);
			$h = $db->diff($t1, $t2, $db->DIFF_TEXT_SPLITTERS);
//			echo '<script> var text_old = ["' . join('", "', $h[0]) . '"], text_new = ["' . join('", "', $h[1]) . '"];</script>';
//			$c = ob_get_contents();
//			ob_end_clean();
			echo mb_convert_encoding($c, 'UTF-8', 'CP1251');
		}
	}

	require_once 'core_diff.php';
	class uDiffIO extends DiffIO {
		var $show_new = true;

		public function outOld($text) {
			if ($this->show_new)
				echo '<del class="old">' . rtrim($text) . '</del> ';
			else
				echo '<ins class="old">' . rtrim($text) . '</ins> ';
		}
		public function outNew($text) {
			if ($this->show_new)
				echo '<ins class="new">' . rtrim($text) . '</ins> ';
			else
				echo '<del class="new">' . rtrim($text) . '</del> ';
		}
		public function outSame($text) {
			echo $text;
//			$text = mb_convert_encoding($text, 'cp1251', 'UTF8');
			$l = strlen($text);
			if ($l >= 50) {
				$s1 = substr($text, 0, 20);
				$s2 = substr($text, $l - 20);
				$text = "$s1...\n...$s2";
			}
			echo $text;//mb_convert_encoding($text, 'UTF8', 'cp1251');
		}

		public function outReplace($diff, $old, $new) {
			$diff->repl[] = array($old, $new);
			if ($this->show_new)
				if (trim($new))
					echo '<span class="new"><span>' . $new . '</span><a class="pin" href="#" onclick="h_edit(this, ' . count($diff->repl) . ');return false;">diff</a></span>';
				else
					;
			else
				if (trim($old))
					echo '<span class="old"><span>' . $old . '</span><a class="pin" href="#" onclick="h_edit(this, ' . count($diff->repl) . ');return false;">diff</a></span>';
		}

	}
?>