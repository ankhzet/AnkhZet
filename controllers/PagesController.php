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
				$this->author_fio = $a['fio'] ? $a['fio'] : '&lt;author&gt;';
				$this->author_link = $a['link'];
			}

			if (!$author)
				locate_to('/authors');

			parent::action($r);
			if ($author)
				View::addKey('title'
				, "<a href=\"/{$this->_name}?author={$author}\">{$this->author_fio}</a> - "
				. ($group ? "<a href=\"/{$this->_name}?group={$group}\">{$this->group_title}</a>" : View::$keys['title'])
				);
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
			header('Content-Length: ' . $len);
			return patternize($this->ID_PATTERN, $row);
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
				echo $cnt;
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
			$_t1 = @gzuncompress/**/($t1);
			if ($_t1 !== false) $t1 = $_t1;
			$_t2 = @gzuncompress/**/($t2);
			if ($_t2 !== false) $t2 = $_t2;
			$t1 = str_replace(array("\n", '<dd>'), array(PHP_EOL, '[n]'), $t1);
			$t2 = str_replace(array("\n", '<dd>'), array(PHP_EOL, '[n]'), $t2);
			$t1 = strip_tags($t1, '<p><br><u><i><s>');
			$t2 = strip_tags($t2, '<p><br><u><i><s>');
			$l1 = strlen($t1);
			$l2 = strlen($t1);
			$chunk = (($chunk = intval($_REQUEST['chunk'])) ? $chunk : 10) * 1024;
			if (($l1 > $chunk) || ($l2 > $chunk)) {
				$c = 0;
				while (($l1 - $c) > $chunk) {
					$c += $chunk;
					$i = strpos($t1, PHP_EOL, $c);
					$j = $i;
					while (abs($j - $i) < 1024)
						$j = strpos($t1, PHP_EOL, $j + 1);

					$mark = substr($t1, $i, $j - $i);
					$u = strpos($t2, $mark);
					if ($u === false) {
						$c = $i;
						continue;
					}
					$t1 = substr($t1, 0, $i) . PHP_EOL . substr($t1, $i);
					$t2 = str_replace($mark, PHP_EOL . $mark, $t2);
				}
			}

			require_once 'core_diff.php';
			ob_start();
//			echo '<pre>';
			$io = new uDiffIO(1024);
			$io->show_new = !$show_old;
			$db = new DiffBuilder($io);
			$h = $db->diff($t1, $t2, intval($_REQUEST['notdeep']) ? $db->DIFF_TEXT_SPLITTERS2 : $db->DIFF_TEXT_SPLITTERS);
			$c = ob_get_contents();
			ob_end_clean();
			echo /*mb_convert_encoding(*/str_replace('[n]', '<br/>', $c);//, 'UTF-8', 'CP1251');
			$old = str_replace("\n", '<br />', join('", "', $h[0]));
			$new = str_replace("\n", '<br />', join('", "', $h[1]));
			echo '<script> var text_old = ["' . $old . '"], text_new = ["' . $new . '"];</script>';
		}
	}

	require_once 'core_diff.php';
	class uDiffIO extends DiffIO {
		var $show_new = true;
		var $context = 100;
		function __construct($context) {
			$this->context = $context ? $context : $this->context;
		}

		function __destruct() {
		}

		function out($text) {
			echo $text;
		}

		public function left($text) {
			$text = mb_convert_encoding($text, 'UTF8', 'cp1251');
			if ($this->show_new)
				$this->out('<span class="old">' . rtrim($text) . '</span> ');
			else
				$this->out('<span class="old">' . rtrim($text) . '</span> ');
		}
		public function right($text) {
			$text = mb_convert_encoding($text, 'UTF8', 'cp1251');
			if ($this->show_new)
				$this->out('<span class="new">' . rtrim($text) . '</span> ');
			else
				$this->out('<span class="new">' . rtrim($text) . '</span> ');
		}
		public function same($text) {
			$text = mb_convert_encoding($text, 'UTF8', 'cp1251');
/**/			$l = strlen($text);
			if ($l >= $this->context) {
				$text = str_replace('&nbsp;', ' ', $text);
				$s1 = safeSubstr($text, $this->context / 2, 100);
				$s2 = safeSubstrl($text, $this->context / 2, 100);
				$text = "<br /><span class=\"context\">{$s1}</span><br />~~~<br /><span class=\"context\">{$s2}</span>";
			}/**/
			$this->out($text);//mb_convert_encoding($text, 'UTF8', 'cp1251');
		}

		public function replace($diff, $old, $new) {
			$old = mb_convert_encoding($old, 'UTF8', 'cp1251');
			$new = mb_convert_encoding($new, 'UTF8', 'cp1251');
			$diff->repl[] = array($old, $new);
			if ($this->show_new)
				if (trim($new))
					$this->out('<span class="new"><span>' . $new . '</span><a class="pin" href="javascript:void(0)" pin="' . count($diff->repl) . '">diff</a></span>');
				else
					;
			else
				if (trim($old))
					$this->out('<span class="old"><span>' . $old . '</span><a class="pin" href="javascript:void(0)" pin="' . count($diff->repl) . '">diff</a></span>');
		}

	}
?>