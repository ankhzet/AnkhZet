<?php
	require_once 'core_history.php';
	require_once 'core_page.php';
	require_once 'core_authors.php';
	require_once 'AggregatorController.php';

	class UpdatesController extends AggregatorController {
		protected $_name = 'updates';

		var $MODER_EDIT = '<span class="pull_right">[<a href="/{%root}/delete/{%id}">{%delete}</a>]</span>';
		var $EDIT_STRINGS = array();
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array();
		var $ID_PATTERN = '';
		var $LIST_ITEM  = '
					<div class="cnt-item">
						<div class="title">
							<span class="head"><span class="pull_left">[<a href="/{%root}/uptodate/{%id}">{%uptodate}</a>]</span><a href="/authors/id/{%author}">{%fio}</a> - <a href="/pages/id/{%pageid}">{%title}</a></span>
							<span class="link">{%size}KB (<span style="{%diff}">{%delta}KB</span>)</span>
							<span class="link size">{%time}</span>
						</div>
						<div class="text">
							{%description}
						</div>
					</div>
';

		const TRACE_PATT = '
		<div class="cnt-item">
			<div class="title">
				<span class="head"><a href="/pages/id/{%id}">{%title}</a></span>
				<span class="link size">{%size}</span>
			</div>
			<div class="text">
				{%description}
			</div>
		</div>
		';
		const UPDATES_CHECK = '<span class="pull_right">[<a href="/updates/check/">{%checkupdates}</a>]</span>';
		const UPDATES_RSS = '<span class="pull_right">[<a href="/api.php?action=rss&channel={%uid}">{%rss}</a>]</span>';

		var $diff_sign = array(-1 => 'color:red', 0 => '', 1 => 'color:green');

		function getAggregator($p = 0) {
			switch ($p) {
			case 0: return HistoryAggregator::getInstance();
			case 1: return AuthorsAggregator::getInstance();
			case 2: return PagesAggregator::getInstance();
			}
		}

		public function makeItem(&$aggregator, &$row) {
			html_escape($row, array('link'));
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			View::addKey('title', $this->author_fio . ' - ' . $row['title']);
			html_escape($row, array('author', 'link'));
			return patternize($this->ID_PATTERN, $row);
		}

		function actionPage($r) {
			$loc = array(
				'checkupdates' => Loc::lget('checkupdates')
			, 'rss' => Loc::lget('RSS')
			, 'uid' => $this->user->ID()
			);
			View::addKey('moder', patternize(self::UPDATES_RSS . ' ' . self::UPDATES_CHECK, $loc));
			$this->query = '`user` = ' . $this->user->ID();
			$aggregator = $this->getAggregator();
			$page = ($page = intval($r[0])) ? $page : 1;
			$this->page = $page;
			$params = array('page' => $page - 1, 'pagesize' => $aggregator->FETCH_PAGE, 'desc' => true);
			$params['collumns'] = 'h.*, p.`author`, a.`fio`, p.`title`, p.`description`, p.`size` as `new_size`, p.`time` as `updated`, (p.`size` <> h.`size`) as `upd`';
			$params['filter'] = '`user` = ' . $this->user->ID();
			$params['order'] = '`upd` desc, `time`';

			$aggregator->TBL_FETCH = '`history` h left join `pages` p on p.`id` = h.`page` left join `authors` a on a.`id` = p.`author`';
			$this->data = $aggregator->fetch($this->prepareFetch($params));

			$total = intval($this->data['total']);
			$last  = intval(ceil($total / $aggregator->FETCH_PAGE));
			$last  = $last < 1 ? 1 : $last;
			if ($last < $page) {
				header('location: /' . $this->_name . '/page/' . $last);
				die();
			}
			$c = count($this->data['data']);

			$n = '';
			if ($c > 0) {
				$idx = array();
				foreach ($this->data['data'] as &$row)
					$idx[] = $row['id'];

//				$updates = $aggregator->fetchUpdates($this->user->ID(), $idx);
//				debug($updates);

				$i = 0;
				foreach($this->data['data'] as &$row) {
					$id = intval($row['id']);
//					$row = array_merge($updates[$id], $row);
					$row['root'] = $this->_name;
					$row['time'] = date('d.m.Y', intval($row['updated']));
					$row['pageid'] = $row['page'];
					$row['page'] = $page;
					$delta = ($s = intval($row['new_size'])) - intval($row['size']);
					$row['delta'] = (($delta < 0) ? '' : '+') . $delta;
					$row['size'] = $s;
					$row['diff'] = $this->diff_sign[sign($delta)];
					$row['uptodate'] = Loc::lget('uptodate');

					$n[] = $this->makeItem($aggregator, $row);
				}
				$n = join($this->LIST_JOINER, $n);
			}

			if ($last > 1) $this->view->pages = '<ul class="pages">' . PHP_EOL . $aggregator->generatePageList($page, $last, $this->_name . '/', $this->link) . '</ul>' . PHP_EOL;

			$this->view->data = $n ? ($this->USE_UL_WRAPPER ? '<ul class="' . $this->_name . '">' . PHP_EOL . $n . PHP_EOL . '</ul>' : $n) : Loc::lget($this->_name . '_nodata');
			$this->view->renderTPL($this->_name . '/index');
		}

		public function actionUptodate($r) {
			$trace = intval($r[0]);
			if (!$trace)
				throw new Exception('Trace ID not specified!');

			$ha = $this->getAggregator(0);
			$pa = $this->getAggregator(2);

			$t = $ha->get($trace, '`page`');
			if (!($page = intval($t['page'])))
				throw new Exception('Trace not found!');

			$ha->upToDate(array($trace));
			locate_to('/' . $this->_name);
		}

		public function actionTrace($r) {
			$author = intval($r[0]);
			if (!$author)
				throw new Exception('Author ID not specified!');

			$added = $this->checkTrace($author);

			if (count($added)) {
				$pa = $this->getAggregator(2);
				$d = $pa->fetch(array('nocalc' => 1, 'desc' => 0, 'filter' => '`id` in (' . join(',', $added) . ')'));
				if ($d['total'])
					foreach ($d['data'] as &$row) {
						$row['size'] = fs(intval($row['size']) * 1024);
						echo patternize(self::TRACE_PATT, $row);
					}
			}
		}

		function checkTrace($author) {
			$ha = $this->getAggregator(0);
			$s = $ha->dbc->select('`pages`', '`author` = ' . $author, '`id` as `0`');
			$idx = array(); // author pages
			if ($s) {
				$f = $ha->dbc->fetchrows($s);
				foreach ($f as &$row)
					$idx[] = intval($row[0]);
			}

			$p = array(); // traced pages
			$d = $ha->fetch(array('nocalc' => 1, 'desc' => 0, 'filter' => '`user` = ' . $this->user->ID(), 'collumns' => '`page` as `0`'));
			if ($d['total'])
				foreach ($d['data'] as &$row)
					$p[] = intval($row[0]);

			$diff = array_diff($idx, $p);
			if (count($diff)) // there are pages, that not traced yet
				$this->tracePages($diff);

			return $diff;
		}

		function tracePages($idx) {
			$ha = $this->getAggregator(0);
			$uid = $this->user->ID();
			$time = time();
			foreach ($idx as $page_id)
				$ha->add(array('user' => $uid, 'page' => $page_id, 'time' => $time));
		}

		function actionCheck($r) {
			$h = $this->getAggregator();
			$a = $h->authorsToUpdate($this->user->ID(), intval($r[0]));
			if (count($a)) {
				require_once 'core_updates.php';
				$u = new AuthorWorker();
				foreach ($a as $id)
					$u->check($id);
			} else
				echo Loc::lget('nothing_to_update') . '<br />';
		}
	}
?>