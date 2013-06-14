<?php
	require_once 'core_bridge.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';

	require_once 'AggregatorController.php';

	class AuthorsController extends AggregatorController {
		protected $_name = 'authors';

		var $EDIT_STRINGS = array('fio', 'link');
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array('link');
		var $ID_PATTERN = '
				<div class="panel">{%fio}{%time}</div>
				<div class="text">
					{%link}<br />
					<a href="/pages?author={%id}">{%pages}</a><br />
					<a href="/{%root}/check/{%id}">{%checkupdates}</a>
				</div>
';
		var $LIST_ITEM  = '
					<div class="cnt-item">
						<div class="title">
							<span class="head">
								<a href="/{%root}/id/{%id}">{%fio}</a>
								<span class="pull_right">[<a href="/updates/trace/{%id}">{%trace}</a>]</span>
							</span>
							<span class="link samlib"><a href="http://samlib.ru/{%link}">/{%link}</a></span>
						</div>
						<div class="text">{%moder}
							<a href="/pages?author={%id}">{%pages}</a> <a href="/{%root}/id/{%id}">{%detail}</a>
						</div>
					</div>
';

		function getAggregator($p = 0) {
			switch ($p) {
			case 0: return AuthorsAggregator::getInstance();
			case 1: return GroupsAggregator::getInstance();
			case 2: return QueueAggregator::getInstance();
			case 3: return PagesAggregator::getInstance();
			}
		}

		public function makeItem(&$aggregator, &$row) {
			html_escape($row, array('fio', 'link'));
			$row['trace'] = Loc::lget('trace');
			$row['pages'] = Loc::lget('pages');
			$row['detail'] = Loc::lget('detail');
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			View::addKey('title', $row['fio']);
			html_escape($row, array('fio', 'link'));
			$row['pages'] = Loc::lget('pages');
			$row['checkupdates'] = Loc::lget('checkupdates');
			return patternize($this->ID_PATTERN, $row);
		}

		public function actionCheck($r) {
			$id = intval($r[0]);
			if (!$id)
				return false;

			require_once 'core_updates.php';
			$u = new AuthorWorker();
			$u->check($id);
		}

		function actionUpdate($r) {
			$limit = intval($r[0]);
			$limit = $limit ? $limit : 1;

			require_once 'core_updates.php';
			$u = new AuthorWorker();
			$left = $u->serveQueue($limit);
			if ($left)
				locate_to('/authors/update/' . $left);
		}

	}
?>