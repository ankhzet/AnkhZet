<?php
	require_once 'core_news.php';
	require_once 'core_comments.php';
	require_once 'ArticleController.php';

	class NewsController extends ArticleController {
		protected $_name = 'news';

		var $EDIT_STRINGS = array('title', 'source', 'content');
		var $EDIT_FILES   = array('preview');
		var $EDIT_REQUIRES= array('title', 'content', 'preview');
		var $ID_PATTERN = '
				<div class="panel">{%source}{%time}<span>Переглядів: {%views}</span></div>
				<img class="photo" src="/data/{%root}/{%preview}" alt="{%title}" />
				<div class="text">
					{%content}
				</div>
';
		var $LIST_ITEM  = '
					<div class="article">
						<img src="/data/{%root}/{%preview}?resolution=160" alt="{%title}" title="{%title}" />
						<div class="title"><a href="/{%root}/id/{%id}">{%title}</a></div>
						<div class="text">{%content}</div>
						{%moder}
						<div class="bottom_link"><a href="/{%root}/id/{%id}">Читати повністю</a></div>
					</div>
';

		function getAggregator($p = 1) {
			return $p ? NewsAggregator::getInstance() : parent::getAggregator();
		}

		public function makeItem(&$aggregator, &$row) {
			$row['content'] = safeSubstr($row['content'], 300);
			html_escape(&$row, array('title', 'source'));
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			$this->updateViews(&$aggregator, intval($row['id']));
			View::addKey('title', $row['title']);
			html_escape(&$row, array('title', 'source'));
			if ($s = $row['source']) $row['source'] = '<b>' . Loc::lget('source') . ':</b> <a href="http://' . $s . '">' . $s . '</a> | ';
			return patternize($this->ID_PATTERN, $row);
		}

	}
?>