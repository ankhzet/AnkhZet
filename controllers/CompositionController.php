<?php
	require_once 'core_page.php';
	require_once 'core_authors.php';
	require_once 'core_history.php';
	require_once 'AggregatorController.php';

	require_once 'core_pagecontroller_utils.php';

	class CompositionController extends AggregatorController {
		protected $_name = 'composition';

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
					<a href="/pages/versions/{%page}" name="anchor{%page}">{%title}</a> (<a href="#top">{%top}</a>)
				<div class="text reader">
					{%content}
					<div class="terminator"></div>
				</div>
			</div>
';
		var $LIST_ITEM  = '
					<div class="cnt-item">
						<div class="title">
							<span class="head"><a href="/authors/id/{%author}">{%fio}</a> - <a href="/{%root}/id/{%composition}">{%title}</a>{%moder}</span>
							<span class="link samlib"><a href="http://samlib.ru/{%autolink}/{%link}">{%autolink}/{%link}</a></span>
						</div>
						<div class="text">
							{%composited}
						</div>
						<div class="text">
							<span class="size">{%size}KB</span>
						</div>
					</div>
';
		const AGGR_PAGES = 0;
		const AGGR_AUTHO = 1;
		const AGGR_GROUP = 2;
		const AGGR_GRAMM = 4;

		function getAggregator($p = self::AGGR_PAGES) {
			switch ($p) {
			case self::AGGR_PAGES: return PagesCompositionAggregator::getInstance();
			case self::AGGR_AUTHO: return AuthorsAggregator::getInstance();
			case self::AGGR_GROUP: return GroupsAggregator::getInstance();
			case self::AGGR_GRAMM: return GrammarAggregator::getInstance();
			}
		}

		function prepareFetch($r) {
			$r['group'] = 'composition';
//			$a = $this->getAggregator();
//			$a->genComposition(array(307, 308, 309, 310, 311, 312));
			return $r;
		}

		public function actionPage($r) {
			if ($this->author) View::addKey('rss-link', "?author={$this->author}");
			if ($this->group) View::addKey('rss-link', "?group={$this->group}");

			return parent::actionPage($r);
		}

		public function action() {
			$author = post_int('author');
			$group = post_int('group');
			$this->group_title = '';
			$this->author = 0;
			$this->group = 0;
			if (!$author && $group) {
				$ga = $this->getAggregator(self::AGGR_GROUP);
				$g = $ga->get($group, '`author`, `title`');
				if ($g) {
					$g['title'] = str_replace(array('@ ', '@'), '', $g['title']);
					$this->group = $group;
					$this->groups[$group] = $g;
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
				$this->authors[$author] = $a;
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

		public function makeItem(&$aggregator, &$row) {
			html_escape($row, array('link'));

			$aa = $this->getAggregator(self::AGGR_AUTHO);
			$author = intval($row['author']);
			if ($author) {
				if (!isset($this->authors[$author])) {
					$a = $aa->get($author, '`id`, `fio`, `link`');
					$this->authors[$author] = $a;
				} else
					$a = &$this->authors[$author];
				$row['fio'] = $a['fio'];
				$row['autolink'] = $a['link'];
			}

			$ga = $this->getAggregator(self::AGGR_GROUP);
			$composition = intval($row['composition']);
			$all = $aggregator->fetch(array(
					'nocalc' => true
				, 'filter' => "`composition` = $composition"
				, 'collumns' => 'page, title, `group`, `size`, `order`, `description`'
				, 'order' => '`order`'
			));
			$pages = array();
			$descriptions = array();
			$size = 0;
			foreach ($all['data'] as $pageRow) {
				$pageRow['root'] = 'pages';
				$join = patternize('<a href="/{%root}/id/{%page}">{%title}</a>', $pageRow);
				$join.= "<br/>{$pageRow['description']}";
				$size += intval($pageRow['size']);
				$group = intval($pageRow['group']);
				if ($group && !isset($this->groups[$group])) {
					$g = $ga->get($group, '`id`, `title`');
					$g['title'] = str_replace(array('@ ', '@'), '', $g['title']);
					$g['root'] = 'pages';
					$this->groups[$group] = patternize(Loc::lget('groups_patt'), $g);
				}
				$join = ($group ? "{$this->groups[$group]} - " : '') . $join;

				$pages[] = $join;
			}
			$row['size'] = $size;
			$c = array('id' => $composition);
			$row['title'] = patternize(Loc::lget('composition'), $c);
			$row['composited'] = join($pages, '<br/>');


			return patternize($this->LIST_ITEM, $row);
		}

		public function noEntry(&$aggregator, $id) {
			$root = $aggregator->fetch(array(
					'nocalc' => true
				, 'filter' => "`composition` = $id and `order` = 0"
				, 'collumns' => 'c.`id`, c.`composition`, c.`title`'
				, 'pagesize' => 1
			));
			if ($root['total'])
				return $root['data'][0];

			throw new Exception('Entry not found');
		}

		public function makeIDItem(&$aggregator, &$row) {
			$composition = intval($row['composition']);
			$version = intval($row['utime']);

			$all = $aggregator->fetch(array(
					'nocalc' => true
				, 'filter' => "`composition` = $composition"
				, 'collumns' => 'c.`id` as `id`, c.`page` as `page`, p.`link` as `link`, p.`author`, p.`title`, p.`time`'
				, 'order' => '`order`'
			));
			$pages = array();
			$anchors = array();
			$content = '';
			foreach ($all['data'] as $pageRow) {
				$pageRow['original'] = Loc::lget('original');
				$pageRow['date'] = Loc::lget('date');
				$pageRow['top'] = Loc::lget('top');
				$titles[] = $pageRow['title'];
				$pages[] = patternize('<a href="/pages/id/{%page}">{%title}</a>', $pageRow);
				$anchors[] = patternize('<li><a href="#anchor{%page}">{%title}</a></li>', $pageRow);

				if (!$this->author_fio) {
					$authorsAggregator = $this->getAggregator(self::AGGR_AUTHO);
					$a = $authorsAggregator->get($author = $pageRow['author'], '`fio`, `link`');
					$this->author_fio = $a['fio'] ? $a['fio'] : '&lt;author&gt;';
					$this->author_link = $a['link'];
				}

				$contents = PageUtils::getPageContents(intval($pageRow['page']));
				$contents = mb_convert_encoding($contents, 'UTF-8', 'CP1251');
				$pageRow['content'] = $contents;
				$pageRow['autolink'] = $this->author_link;
				$pageRow['time'] = date('d.m.Y', $pageRow['time']);
				$content .= patternize($this->ID_PATTERN, $pageRow);
			}
			array_unshift($titles, $this->author_fio);

			View::addKey('meta-keywords', join($titles, ', '));
			View::addKey('meta-description', join($titles, ', '));

			View::addKey('title', "<a href=\"/authors/id/{$row['author']}\">{$this->author_fio}</a> - {$row['title']}");

			View::addKey('anchor-table', join($anchors));
			View::addKey('preview', $content);
			View::addKey('meta-keywords', join($titles, ', '));
			$this->view->renderTPL('composition/view');
			return '';
		}


	}
