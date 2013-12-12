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
						<span class="link size u2">{%size}KB {%delta}</span>
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
		const UPDATES_HIDDEN = '<span class="pull_right">[<a href="/updates?hidden={%hidden}{%muid}">{%check}</a>{%opposite}]</span>';
		const UPDATES_OPPOSITE = ' <sup><a href="/updates?hidden={%hidden}{%opposite:filter}">{%opposite}</a></sup>';

		const AUTHOR_FILTER = '<a href="/updates?{%hidden}author={%id}{%uid}{%opposite:filter}" class="filter {%color}">{%fio}</a> <sup>({%count})</sup>';

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
			if ($this->userModer && ($muid = post_int('for_user'))) $uid = $muid;

			View::addKey('rss-link', "?channel=$uid");

			$author = uri_frag($_REQUEST, 'author', 0);
			$hidden = uri_frag($_REQUEST, 'hidden', 0);
			$min_s = uri_frag($_REQUEST, 'min_size', 0);
			$max_s = uri_frag($_REQUEST, 'max_size', 0);
			$hidden = uri_frag($_REQUEST, 'hidden', 0);
			$hidden_f = intval(!$hidden);
			$author_f = $author ? " and p.`author` = $author" : '';
			$minsize_f = $min_s ? " and p.`size` >= $min_s" : '';
			$maxsize_f = $max_s ? " and p.`size` <= $max_s" : '';
			$this->query = "`user` = $uid";
			$aggregator = $this->getAggregator();
			$this->page = $page = uri_frag($r, 0, 1);
			$l = array();
			if ($author) $l['author'] = "author=$author";
			if ($muid) $l['for_user'] = "for_user=$uid";
			if ($min_s) $l['min_size'] = "min_size=$min_s";
			if ($max_s) $l['max_size'] = "max_size=$max_s";
			$filter_l = $l;
			if ($hidden) $l['hidden'] = "hidden=1";
			$this->link = (!$l) ? '' : '?' . join('&', $l);
			$params = array('page' => $page - 1, 'pagesize' => $aggregator->FETCH_PAGE, 'desc' => true);
			$params['collumns'] = 'h.*, p.`author`, a.`fio`, p.`title`, p.`description`, p.`size` as `new_size`, p.`time` as `updated`, (p.`size` <> h.`size`) as `upd`';
			$params['order'] = '`upd` desc, `time`';
			$params['filter'] = "`user` = $uid and `trace` = $hidden_f $author_f $minsize_f $maxsize_f";

			$aggregator->TBL_FETCH = '`history` h left join `pages` p on p.`id` = h.`page` left join `authors` a on a.`id` = p.`author`';
			$this->data = $aggregator->fetch($this->prepareFetch($params));

			// counting not traced pages if "Traced" are shown and vice versa
			$params = array('pagesize' => 1, 'desc' => 0);
			$params['collumns'] = 'count(h.id) as c';
			$params['filter'] = "`user` = $uid and `trace` = $hidden $author_f $minsize_f $maxsize_f";
			$aggregator->TBL_FETCH = '`history` h left join `pages` p on p.`id` = h.`page`';
			$others = $aggregator->fetch($params);
			$others = intval($others['data'][0]['c']);

			$filters_opposite = array();
			if ($author) {
				$aa = $this->getAggregator(1);
				$adata = $aa->get($author, 'fio');
				$filters_opposite[] = $adata['fio'];
			}
			if ($min_s || $max_s) {
				$min_sl = fs($min_s * 1024);
				$max_sl = fs($max_s * 1024);
				$filters_opposite[] = $max_s ? "$min_s-$max_sl" : "$min_sl+";
			}
			$filters_opposite = $filters_opposite ? join(', ', $filters_opposite) . ': ' : '';
			$opposite = "$filters_opposite$others";
			$loc = array(
				'checkupdates' => Loc::lget('checkupdates')
			, 'check' => Loc::lget($hidden ? 'checktraced' : 'checkhidden')
			, 'uid' => $uid
			, 'muid' => $muid ? "&for_user=$uid" : ''
			, 'hidden' => $hidden_f
			, 'author' => $author ? "&author=$author" : ''
			, 'opposite' => $opposite
			, 'opposite:filter' => $filter_l ? '&' . join('&', $filter_l) : ''
			);
			$loc['opposite'] = patternize(self::UPDATES_OPPOSITE, $loc);

			View::addKey('moder', patternize(self::UPDATES_CHECK . ' ' . self::UPDATES_HIDDEN, $loc));


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
				$pp1 = '<sup style="font-weight: normal;{%diff}">{%delta}KB</sup>';
				foreach($this->data['data'] as &$row) {
					$id = intval($row['id']);
//					$row = array_merge($updates[$id], $row);
					$row['root'] = $this->_name;
					$row['time'] = date('d.m.Y', intval($row['updated']));
					$row['pageid'] = $row['page'];
					$row['page'] = $page;
					$delta = ($s = intval($row['new_size'])) - intval($row['size']);
					$row['size'] = $s;
					if ($delta != 0) {
//						$row['delta'] = (($delta < 0) ? '' : '+') . $delta;
						$delta = array('diff' => $this->diff_sign[sign($delta)], 'delta' => (($delta < 0) ? '' : '+') . $delta);
						$row['delta'] = patternize($pp1, $delta);
					} else
						$row['delta'] = '';
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
			. '<a href="javascript:void(0)" alt="/updates/hide?traced=' . $hidden_f . '" confirm="1" class="multi link">Не отслеживать</a></div>'
			. '<ul class="pages">'
			. PHP_EOL . (($last > 1) ? $aggregator->generatePageList($page, $last, $this->_name . '/', $this->link) : '<li>&nbsp;</li>') . '</ul>' . PHP_EOL;

			$this->view->data = $n ? $n : Loc::lget("{$this->_name}_nodata");

			$params = array('desc' => 0, 'nocalc' => true);
			$params['collumns'] = 'p.`author` as `0`, count(p.author) as `1`';
			$params['filter'] = "`user` = $uid and `trace` = $hidden_f $minsize_f $maxsize_f group by p.`author`";
			$aggregator->TBL_FETCH = '`history` h left join `pages` p on p.`id` = h.`page`';
			$authors = $aggregator->fetch($params);
			$a_c = array();
			$a_c[0] = 0;
			if ($authors['total'])
				foreach ($authors['data'] as $row) {
					$a_c[intval($row[0])] = $cnt = intval($row[1]);
					$a_c[0] += $cnt;
				}

			$a = $aggregator->authorsToUpdate($uid, 1, 1, $hidden_f);
			if (count($a)) {
				$aa = $this->getAggregator(1);
				$d = $aa->get($a, '`id`, `fio`');
				$a = array();
				if (!!$d) {
					if ($min_s || $max_s) {
						$min_sl = fs($min_s * 1024);
						$max_sl = fs($max_s * 1024);
						$filters_size = $max_s ? "$min_s-$max_sl" : "$min_sl+";
					} else
						$filters_size = '';

					View::addKey('filter-size', $filters_size ? "<sup>($filters_size)</sup>" : '');

					unset($filter_l['author']);
					$row = array(
						'hidden' => $hidden ? 'hidden=1&' : ''
					, 'uid' => $muid ? "&for_user=$uid" : ''
					, 'opposite:filter' => $filter_l ? '&' . join('&', $filter_l) : ''
					);
					$p = patternize(self::AUTHOR_FILTER, $row);
					$dummy = array('color' => (!$author) ? 'selected' : '', 'id' => 0, 'fio' => Loc::lget('authors-all'), 'count' => $a_c[0]);
					$a[] = patternize($p, $dummy);
					foreach ($d as &$row) {
						$row['color'] = (($author_id = $row['id']) == $author) ? 'selected' : '';

						$row['count'] = (isset($a_c[$author_id]) ? $a_c[$author_id] : 0);
						$a[] = patternize($p, $row);
					}
				}
				View::addKey('authors', '<span>' . join('</span>, <span class="nowrap">', $a) . '</span>');
			} else
				View::addKey('authors', '#');

			$p = array(
				'hidden' => $hidden ? '&hidden=1&' : ''
			, 'uid' => $muid ? "&for_user=$uid" : ''
			, 'author' => $author ? "&author=$author" : ''
			);

			$i = array(
				array(0, 0, Loc::lget('size-any'), '') // as 0
			, array(0, 20, '0-20Kb', ' (micro)') // as 1
			, array(20, 100, '20-100Kb', ' (mini)') // as 2
			, array(20, 0, '20Kb+', '') // as 3
			, array(100, 0, '100Kb+', ' (semi)') // as 4
			, array(200, 0, '200Kb+', ' (maxi)') // as 5
			, array(500, 0, '500Kb+', ' (book)') // as 6
			);

			$a_s = array();
			$dbc = msqlDB::o();
			foreach ($i as $idx => $range) {
				$min_f = $range[0] ? " and p.size >= {$range[0]}" : '';
				$max_f = $range[1] ? " and p.size <= {$range[1]}" : '';
				$s = $dbc->query("
					select count(p.`id`) as `c`
						from `history` h left join `pages` p on p.`id` = h.`page`
						where `user` = $uid $author_f and `trace` = $hidden_f $min_f $max_f
				");
				$a_s[$idx] = $s ? @mysql_result($s, 0) : 0;
			}

			if ($author) {
				$aa = $this->getAggregator(1);
				$adata = $aa->get($author, 'fio');
				$filters_author = $adata['fio'];
			} else
				$filters_author = '';
			View::addKey('filter-author', $filters_author ? "<sup>($filters_author)</sup>" : '');
			$s = array();
			foreach ($i as $idx => $range) {
				$data = array_merge($p, array(
					'root' => $this->_name
				, 'from' => $range[0] ? "min_size={$range[0]}" : ''
				, 'to' => $range[1] ? "&max_size={$range[1]}" : ''
				, 'label' => $range[2]
				, 'name' => " <sup>(" . $a_s[$idx] . ")</sup>" . "<span style=\"font-weight: normal\">$range[3]</span>"
				, 'color' => ($min_s == $range[0] && $max_s == $range[1]) ? 'selected' : ''
				));
				$s[] = patternize("<a class=\"filter {%color}\" href=\"/{%root}?{%from}{%to}{%hidden}{%author}{%uid}\">{%label}</a>{%name}", $data);
			}
			View::addKey('sizes', join(', ', $s));

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
			$trace = $new_only ? 0 : post_int('trace');
			if ($aid || $pid || !$this->userModer)
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
//			if ($left)
//				locate_to("/updates/pages/$left");
		}

	}
?>