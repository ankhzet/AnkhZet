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
						<span class="head">
							<span class="multi"><input type=checkbox name="id[]" value="{%id}" /></span>
							<a href="/authors/id/{%author}">{%fio}</a> - <a href="/pages/version/{%pageid}">{%title}</a>{%mark}
							<span class="pull_right">[ <a href="/{%root}/uptodate?id[]={%id}">{%uptodate}</a> ]</span>
						</span>
						<span class="head small">
						<span class="link size u2">{%size}KB (<span style="{%diff}">{%delta}KB</span>)</span>
						<span class="link size">{%time}</span>
						</span>
					</div>
					<div class="text">
						{%description}
					</div>
				</div>
';

		const TRACE_PATT = '
		<div class="cnt-item">
			<div class="title">
				<span class="head">UID#<a href="/user/{%user}">{%user:name}</a> [<a href="/authors/id/{%author}">{%fio}</a> - <a href="/pages/id/{%id}">{%title}</a>]</span>
				<span class="link size">{%size}</span>
			</div>
			<div class="text">
				{%description}
			</div>
		</div>
		';
		const UPDATES_CHECK = '<span class="pull_right">[<a href="/updates/trace">{%checkupdates}</a>]</span>';
		const UPDATES_HIDDEN = '<span class="pull_right">[<a href="/updates?hidden={%hidden}">{%check}</a>]</span>';
		const UPDATES_RSS = '<span class="pull_right">[<a href="/rss.xml?channel={%uid}">{%rss}</a>]</span>';

		const AUTHOR_FILTER = '<a href="/updates?{%hidden}author={%id}" class="filter {%color}">{%fio}</a>';

		var $diff_sign = array(-1 => 'color:red', 0 => '', 1 => 'color:green');

		function getAggregator($p = 0) {
			switch ($p) {
			case 0: return HistoryAggregator::getInstance();
			case 1: return AuthorsAggregator::getInstance();
			case 2: return PagesAggregator::getInstance();
			case 3:
				require_once 'core_updates.php';
				return UpdatesAggregator::getInstance();
			}
		}

		public function makeIDItem(&$aggregator, &$row) {
			View::addKey('title', $this->author_fio . ' - ' . $row['title']);
			html_escape($row, array('author', 'link'));
			return patternize($this->ID_PATTERN, $row);
		}

		function actionPage($r) {
			$uid = $this->user->ID();
			$author = uri_frag($_REQUEST, 'author', 0);
			$hidden = uri_frag($_REQUEST, 'hidden', 0);
			$hidden_f = intval(!$hidden);
			$author_f = $author ? " and p.`author` = $author" : '';
			$loc = array(
				'checkupdates' => Loc::lget('checkupdates')
			, 'check' => Loc::lget($hidden ? 'checktraced' : 'checkhidden')
			, 'rss' => Loc::lget('RSS')
			, 'uid' => $uid
			, 'hidden' => $hidden_f
			);

			View::addKey('moder', patternize(self::UPDATES_RSS . ' ' . self::UPDATES_CHECK . ' ' . self::UPDATES_HIDDEN, $loc));
			$this->query = '`user` = ' . $this->user->ID();
			$aggregator = $this->getAggregator();
			$this->page = $page = uri_frag($r, 0, 1);
			$l = array();
			if ($hidden) $l[] = "hidden=1";
			if ($author) $l[] = "author=$author";
			$this->link = (!$l) ? '' : '?' . join('&', $l);
			$params = array('page' => $page - 1, 'pagesize' => $aggregator->FETCH_PAGE, 'desc' => true);
			$params['collumns'] = 'h.*, p.`author`, a.`fio`, p.`title`, p.`description`, p.`size` as `new_size`, p.`time` as `updated`, (p.`size` <> h.`size`) as `upd`';
			$params['filter'] = "`user` = $uid and `trace` = $hidden_f $author_f";
			$params['order'] = '`upd` desc, `time`';

			$aggregator->TBL_FETCH = '`history` h left join `pages` p on p.`id` = h.`page` left join `authors` a on a.`id` = p.`author`';
			$this->data = $aggregator->fetch($this->prepareFetch($params));

			$last  = intval(ceil($this->data['total'] / $aggregator->FETCH_PAGE));
			$last  = $last < 1 ? 1 : $last;
			if ($last < $page)
				locate_to("/{$this->_name}/page/{$last}");

			$c = count($this->data['data']);

			$n = '';
			if ($c > 0) {
				$idx = array();
				foreach ($this->data['data'] as &$row)
					$idx[] = $row['id'];

				$pa = $this->getAggregator(2);
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
					$row['mark'] = $pa->traceMark($uid, $row['trace'], $row['pageid'], $row['author']);
					$row['untrace'] = Loc::lget($row['trace'] ? 'untrace' : 'trace');

					$n[] = patternize($this->LIST_ITEM, $row);
				}
				$n = join($this->LIST_JOINER, $n);
			}

			$this->view->pages = ''
			. '<div style="float: left; margin: 12px 0 0 5px; position: relative;">'
			. '<input type=checkbox class="multi-check" /> С отмеченными: '
			. '<a href="javascript:void(0)" alt="/updates/uptodate" class="multi link">Прочитано</a> | '
			. '<a href="javascript:void(0)" alt="/updates/hide" confirm="1" class="multi link">Не отслеживать</a></div>'
			. '<ul class="pages">'
			. PHP_EOL . (($last > 1) ? $aggregator->generatePageList($page, $last, $this->_name . '/', $this->link) : '<li>&nbsp;</li>') . '</ul>' . PHP_EOL;

			$this->view->data = $n ? $n : Loc::lget("{$this->_name}_nodata");

			$a = $aggregator->authorsToUpdate($this->user->ID(), 1, 1, $hidden_f);
			if (count($a)) {
				$aa = $this->getAggregator(1);
				$d = $aa->get($a, '`id`, `fio`');
				$a = array();
				if (!!$d) {
					$p = str_replace('{%hidden}', $hidden ? 'hidden=1&' : '', self::AUTHOR_FILTER);
					foreach ($d as &$row) {
						$row['color'] = ($row['id'] == $author) ? 'selected' : '';
						$a[] = patternize($p, $row);
					}
				}
				$this->view->authors = join(', ', $a);
			} else
				$this->view->authors = '#';

			$this->view->renderTPL("{$this->_name}/index");
		}

		public function actionUptodate($r) {
			$trace = uri_frag($_REQUEST, 'id', 0, 0);
			if (!$trace)
				throw new Exception('Trace ID not specified!');

			$idx = array();
			foreach($trace as $id)
				$idx[] = intval($id);

			$ha = $this->getAggregator(0);
			$ha->upToDate($idx);
			if (uri_frag($_REQUEST, 'silent')) die();
			locate_to("/{$this->_name}");
		}

		public function actionHide($r) {
			$trace = uri_frag($_REQUEST, 'id', 0, 0);
			if (!$trace)
				throw new Exception('Trace ID not specified!');

			$idx = array();
			foreach($trace as $id)
				$idx[] = intval($id);

			$ha = $this->getAggregator(0);
			$ha->markTrace($idx, intval(!uri_frag($_REQUEST, 'traced')));
			if (uri_frag($_REQUEST, 'silent')) die();
			locate_to("/{$this->_name}");
		}

		public function actionTrace($r) {
			$aid = uri_frag($r, 0);
			$pid = uri_frag($r, 1);
			$new_only = !isset($_REQUEST['trace']);
			$trace = $new_only ? 1 : post_int('trace');
			if ($aid || $pid)
				$this->traceForUser($this->user->ID(), $aid, $pid, $trace, $new_only);
			else {
				$o = msqlDB::o();
				$s = $o->select('users', '1', '`id`');
				$r = $s ? $o->fetchrows($s) : array();
				if (!!$r)
					foreach ($r as &$row)
						$this->traceForUser(intval($row['id']), 0, 0, $trace, true);
			}
		}

		function traceForUser($uid, $aid, $pid, $trace, $new_only) {
			$ha = $this->getAggregator(0);
			$a = array();
			if (!$aid) {
				$s = $ha->dbc->select('`history` h, `pages` p', "h.`user` = $uid and h.`page` = p.`id` group by p.`author`", 'p.`author` as `0`');
				while (($row = @mysql_fetch_row($s)) !== false)
					$a[] = intval($row[0]);
			} else
				$a = array($aid);

			$p = array();
			foreach ($a as $author) {
				$added = $ha->traceNew($author, $uid, $pid, $trace, $new_only);

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
					$row['user'] = $uid;
					$row['user:name'] = User::get($uid)->readable();
					echo patternize(self::TRACE_PATT, $row);
				}
			}
		}

		function actionAuthors($r) {
			require_once 'core_updates.php';
			$u = new AuthorWorker();

//			msqlDB::o()->debug = 1;
			$h = $this->getAggregator();
			$a = $h->authorsToUpdate(0, uri_frag($r, 0));
			if (!!$a)
				foreach ($a as $id)
					if (!$u->check($id)) {
						echo Loc::lget('halting') . '<br />';
						return;
					}

			$g = $h->groupsToUpdate(uri_frag($r, 0));
			if (!!$g)
				foreach ($g as $id)
					if (!$u->checkGroup($id)) {
						echo Loc::lget('halting') . '<br />';
						return;
					}

			if (!$a && !$g)
				echo Loc::lget('nothing_to_update') . '<br />';
		}

		function actionPages($r) {
			$limit = uri_frag($r, 0, 1);

			require_once 'core_updates.php';
			$u = new AuthorWorker();
			$left = $u->serveQueue($limit);
			if ($left)
				locate_to("/authors/update/$left");
		}

	}
?>