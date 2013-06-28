<?php
	require_once 'core_history.php';
	require_once 'core_page.php';
	require_once 'core_authors.php';
	require_once 'AggregatorController.php';

	class UpdatesController extends AggregatorController {
		protected $_name = 'updates';

		var $USE_UL_WRAPPER = false;
		var $MODER_EDIT = '<span class="pull_right">[<a href="/{%root}/delete/{%id}">{%delete}</a>]</span>';
		var $EDIT_STRINGS = array();
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array();
		var $ID_PATTERN = '';
		var $LIST_ITEM  = '
				<div class="cnt-item">
					<div class="title">
						<span class="head">
							<span class="multi"><input type=checkbox name="id[]" value="{%id}" /></span>
							<a href="/authors/id/{%author}">{%fio}</a> - <a href="/pages/version/{%pageid}">{%title}</a>
							<span class="pull_right">[<a href="/{%root}/hide?id[]={%id}&traced={%trace}">{%untrace}</a> | <a href="/{%root}/uptodate?id[]={%id}">{%uptodate}</a>]</span>
						</span>
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
				<span class="head">[<a href="/authors/id/{%author}">{%fio}</a> - <a href="/pages/id/{%id}">{%title}</a>]</span>
				<span class="link size">{%size}</span>
			</div>
			<div class="text">
				{%description}
			</div>
		</div>
		';
		const UPDATES_CHECK = '<span class="pull_right">[<a href="/updates/trace">{%checkupdates}</a>]</span>';
		const UPDATES_HIDDEN = '<span class="pull_right">[<a href="/updates?hidden={%hidden}">{%check}</a>]</span>';
		const UPDATES_RSS = '<span class="pull_right">[<a href="/api.php?action=rss&channel={%uid}">{%rss}</a>]</span>';

		var $diff_sign = array(-1 => 'color:red', 0 => '', 1 => 'color:green');

		function getAggregator($p = 0) {
			switch ($p) {
			case 0: return HistoryAggregator::getInstance();
			case 1: return AuthorsAggregator::getInstance();
			case 2: return PagesAggregator::getInstance();
			}
		}

		public function makeIDItem(&$aggregator, &$row) {
			View::addKey('title', $this->author_fio . ' - ' . $row['title']);
			html_escape($row, array('author', 'link'));
			return patternize($this->ID_PATTERN, $row);
		}

		function actionPage($r) {
			$hidden = intval($_REQUEST['hidden']);
			$loc = array(
				'checkupdates' => Loc::lget('checkupdates')
			, 'check' => Loc::lget($hidden ? 'checktraced' : 'checkhidden')
			, 'rss' => Loc::lget('RSS')
			, 'uid' => $this->user->ID()
			, 'hidden' => !$hidden
			);
			View::addKey('moder', patternize(self::UPDATES_RSS . ' ' . self::UPDATES_CHECK . ' ' . self::UPDATES_HIDDEN, $loc));
			$this->query = '`user` = ' . $this->user->ID();
			$aggregator = $this->getAggregator();
			$page = ($page = intval($r[0])) ? $page : 1;
			$this->page = $page;
			$this->link = $hidden ? '?hidden=1' : '';
			$params = array('page' => $page - 1, 'pagesize' => $aggregator->FETCH_PAGE, 'desc' => true);
			$params['collumns'] = 'h.*, p.`author`, a.`fio`, p.`title`, p.`description`, p.`size` as `new_size`, p.`time` as `updated`, (p.`size` <> h.`size`) as `upd`';
			$params['filter'] = '`user` = ' . $this->user->ID() . ' and `trace` = ' . ($hidden ? 0 : 1);
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
					$row['untrace'] = Loc::lget($row['trace'] ? 'untrace' : 'trace');

					$n[] = patternize($this->LIST_ITEM, $row);
				}
				$n = join($this->LIST_JOINER, $n);
			}

			if ($last > 1) $this->view->pages = '<ul class="pages">'
			. '<li style="float: left; margin: 0 -100% 0 5px; position: relative;"><input type=checkbox class="multi-check" /> С отмеченными: <a href="javascript:void(0)" alt="/updates/uptodate" class="multi link">Прочитано</a> | <a href="javascript:void(0)" alt="/updates/hide" confirm="1" class="multi link">Не отслеживать</a></li>'
			. PHP_EOL . $aggregator->generatePageList($page, $last, $this->_name . '/', $this->link) . '</ul>' . PHP_EOL;

			$this->view->data = $n ? ($this->USE_UL_WRAPPER ? '<ul class="' . $this->_name . '">' . PHP_EOL . $n . PHP_EOL . '</ul>' : $n) : Loc::lget($this->_name . '_nodata');
			$this->view->renderTPL($this->_name . '/index');
		}

		public function actionUptodate($r) {
			$trace = $_REQUEST['id'];
			if (!@count($trace))
				throw new Exception('Trace ID not specified!');

			$idx = array();
			foreach($trace as $id)
				$idx[] = intval($id);

			$ha = $this->getAggregator(0);
			$ha->upToDate($idx);
			if ($_REQUEST['silent']) die();
			locate_to('/' . $this->_name);
		}

		public function actionHide($r) {
			$trace = $_REQUEST['id'];
			if (!@count($trace))
				throw new Exception('Trace ID not specified!');

			$idx = array();
			foreach($trace as $id)
				$idx[] = intval($id);

			$ha = $this->getAggregator(0);
			$ha->markTrace($idx, !intval($_REQUEST['traced']));
			if ($_REQUEST['silent']) die();
			locate_to('/' . $this->_name);
		}

		public function actionTrace($r) {
			$ha = $this->getAggregator(0);
			$uid = $this->user->ID();
			$aid = intval($r[0]);
			$a = array();
			if (!$aid) {
				$s = $ha->dbc->select('`history` h, `pages` p', "h.`user` = $uid and h.`page` = p.`id` group by p.`author`", 'p.`author` as `0`');
				while (($row = @mysql_fetch_row($s)) !== false)
					$a[] = intval($row[0]);
			} else
				$a = array($aid);

			$p = array();
			foreach ($a as $author) {
				$added = $ha->traceNew($author, $uid);

				if (count($added)) {
					$pa = $this->getAggregator(2);
					$d = $pa->fetch(array('nocalc' => 1, 'desc' => 0, 'filter' => '`id` in (' . join(',', $added) . ')'));
					if ($d['total'])
						$p = array_merge($p, $d['data']);
				}
			}

			if (count($p)) {
				View::addKey('title', Loc::lget('pages_to_check'));
				$aa = $this->getAggregator(1);
				$d = $aa->get($a, '`id`, `fio`');
				$f = array();
				foreach ($d as $row)
					$f[intval($row['id'])] = $row['fio'];

				foreach ($p as &$row) {
					$row['size'] = fs(intval($row['size']) * 1024);
					$row['fio'] = $f[intval($row['author'])];
					echo patternize(self::TRACE_PATT, $row);
				}
			}
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