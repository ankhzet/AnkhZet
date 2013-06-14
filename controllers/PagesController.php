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
							<span class="head"><a href="/{%root}/id/{%id}">{%title}</a>{%moder}</span>
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
				<a href="/{%root}/version/{%page}?action=diff&version={%version}"{%last}>{%diff}</a> &darr;
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

			View::addKey('title', '<a href="/authors">' . $this->author_fio . '</a> - ' . $row['title']);
			html_escape($row, array('author', 'link'));
			$row['date'] = Loc::lget('date');
			$row['original'] = Loc::lget('original');
			$row['fio'] = $this->author_fio;
			$row['autolink'] = $this->author_link;
			$row['content'] = @file_get_contents(SUB_DOMEN . '/cache/pages/' . $row['id'] . '/last.html');
			if ($row['content']) {
				$row['content'] = /*gzuncompress*/(@file_get_contents(SUB_DOMEN . '/cache/pages/' . $row['id'] . '/last.html'));
				$row['content'] = mb_convert_encoding($row['content'], 'UTF-8', 'CP1251');
			}
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

			default:
				rsort($p);
				$l = count($p) - 1;
				$row = array('root' => $this->_name, 'page' => $page);
				foreach ($p as $idx => $version) {
					$row = array_merge($data, $row);
					$row['view'] = Loc::lget('view');
					$row['diff'] = Loc::lget('diff');
					$row['version'] = $version;
					$row['timestamp'] = date('d.m.Y h:i:s', $version);
					$row['size'] = fs(filesize("$storage/$version.html"));
					echo patternize(($idx != $l) ? self::VERSION_PATT : self::VERSION_PATT2, $row);
				}
			}
		}
	}
?>