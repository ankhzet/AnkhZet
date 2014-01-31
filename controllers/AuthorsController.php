<?php
	require_once 'core_bridge.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';
	require_once 'core_pagecontroller_utils.php';
	require_once 'core_history.php';

	require_once 'AggregatorController.php';

	define('PAGES_PER_GROUP', 4);

	class AuthorsController extends AggregatorController {
		protected $_name  = 'authors';

		var $ALWAYS_ADD   = 1;
		var $USE_UL_WRAPPER = false;
		var $EDIT_STRINGS = array('fio', 'link');
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array('link');
		var $ID_PATTERN = '
				<div class="cnt-item">
					<div class="title">
						<span class="head">
							{%fio}
							<span class="pull_right">[<a href="/pages?author={%id}">{%pages}</a>|<a href="/{%root}/chronology/{%id}">{%chronology}</a>|<a href="/{%root}/check/{%id}">{%checkupdates}</a>]</span>
						</span>
						<span class="link size">{%time}</span>
						<span class="link samlib"><a href="http://samlib.ru/{%link}">/{%link}</a></span>
					</div>
				</div>
			{%groups}
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
						[ <a href="/pages?author={%id}">{%pages}</a> | <a href="/{%root}/id/{%id}">{%detail}</a> ]
					</div>
				</div>
';
		var $GROUP_ITEM  = '
				<div class="cnt-item">
					<div class="title">
						<span class="head">
							{%g:inline}<a href="/pages?group={%g:id}">{%g:title}</a>
						</span>
						<span class="link samlib"><a href="http://samlib.ru{%g:link}">{%g:link}</a></span>
					</div>
					<div class="text">
						{%g:desc}
						{%g:pages}
					</div>
				</div>
';

		function getAggregator($p = 0) {
			switch ($p) {
			case 0: return AuthorsAggregator::getInstance();
			case 1: return GroupsAggregator::getInstance();
			case 2: return QueueAggregator::getInstance();
			case 3: return PagesAggregator::getInstance();
			case 4: return HistoryAggregator::getInstance();
			}
		}

		function prepareFetch($filters) {
			$filters['order'] = 'link';
			$filters['desc'] = false;
			return $filters;
		}

		public function makeItem(&$aggregator, &$row) {
			html_escape($row, array('fio', 'link'));
			$row['trace'] = Loc::lget('trace');
			$row['pages'] = Loc::lget('pages');
			$row['detail'] = Loc::lget('detail');
			return patternize($this->LIST_ITEM, $row);
		}

		public function actionPage($r) {
			$filter = post('by_name');
			if ($filter && ($filter = preg_replace('/[^\p{L}]/iu', '', $filter))) {
				$filter = mb_substr($filter, 0, 1);
				$this->query = "`fio` like '$filter%'";
				$this->link = '?by_name=' . $filter;
			}

			$c = msqlDB::o();
			$s = $c->select('authors', ' group by `0`', 'ucase(substr(`fio`, 1, 1)) as `0`, count(`id`) as `1`');
			$a = array();
			$i = 0;
			if ($s)
				while (($row = @mysql_fetch_row($s)) !== false) {
					$c = $row[0];
					$o = $row[1];
					$color = ($c == $filter) ? ' selected' : '';
//					$c = strtoupper($c);
					$a[] = "<a href=\"/authors?by_name=$c\" class=\"filter$color\">$c</a> <sup>($o)</sup>";
				}
			$this->view->fio_filter = join(', ', $a);

			return parent::actionPage($r);
		}

		public function makeIDItem(&$aggregator, &$row) {
			View::addKey('title', "<a href=\"/authors/id/{$row['id']}\">{$row['fio']}</a>");

			html_escape($row, array('fio', 'link'));
			$row['pages'] = Loc::lget('pages');
			$row['checkupdates'] = Loc::lget('checkupdates');
			$row['chronology'] = mb_strtolower(Loc::lget('titles.authorschronology'));

			$ga = $this->getAggregator(1);
			$g = $ga->fetch(array('nocalc' => true, 'order' => 'replace(`title`, "@", ""), `time`', 'desc' => 1, 'filter' => '`author` = ' . ($author = $row['id']), 'collumns' => '`id`, `title`, `link`, `description`'));
			$a = array();
			$pa = $this->getAggregator(3);
			$ha = $this->getAggregator(4);
			$uid = $this->user->ID();

			$s = $pa->dbc->select(
				"pages p left join groups g on g.author = $author and g.id = p.group"
			, "p.author = $author and g.id is null group by p.group"
			, 'p.group as `id`');
			$f = $s ? $pa->dbc->fetchrows($s) : array();
			$loc_deleted = Loc::lget('deleted_group');
			foreach ($f as $rowg) {
				$g['total'] = ($group_idx = $g['total']) + 1;

				$g['data'][] = array('id' => $g_id = intval($rowg['id']), 'link' => '', 'title' => "@DELETED#$g_id", 'description' => $loc_deleted);
			}

			if ($g['total'])
				foreach ($g['data'] as &$rowg) {
					$row['g:id'] = ($group_id = intval($rowg['id']));
					$row['g:link'] = ($rowg['link'] && (strpos($rowg['link'], 'type') == false)) ? "/{$row['link']}/{$rowg['link']}" : "";
					$row['g:inline'] = (strpos($rowg['title'], '@') !== false) ? '<span class="inline-mark">@</span>' : '';
					$row['g:title'] = str_replace(array('@ ', '@'), '', $rowg['title']);
					$row['g:desc'] = $rowg['description'];
					$d = $pa->fetch(array('pagesize' => PAGES_PER_GROUP, 'desc' => 1
					, 'filter' => "`group` = $group_id"
					, 'collumns' => '`id`, `title`'
					));
					$t = $d['total'];
					if ($t > 4) {
						unset($d['data'][3]);
						$t++;
					}
					$u = array();
					if ($t) {
						if ($uid) {
							$r = array();
							foreach ($d['data'] as $rowp)
								$r[] = intval($rowp['id']);

							$r = join(',', $r);
							$o = $ha->fetch(array('nocalc' => 1, 'desc' => 0, 'filter' => "`user` = $uid and `page` in ($r)", 'collumns' => '`page`, `trace`'));
							$r = array();
							if ($o['total'])
								foreach ($o['data'] as &$rowp)
									$r[intval($rowp['page'])] = intval($rowp['trace']);
						}

						foreach ($d['data'] as $rowp) {
							$id = $rowp['id'];
							$trace = $uid ? (isset($r[$id]) ? $r[$id] : -1) : -1;
							$rowp['mark'] = PageUtils::traceMark($uid, $trace, $id, $row['id']);
							$u[] = patternize('&rarr; <a href="/pages/version/{%id}">{%title}</a>{%mark}', $rowp);
						}

						if ($t > PAGES_PER_GROUP) {
							$t = array('left' => $t - PAGES_PER_GROUP, 'link' => "/pages?group=$group_id");
							$u[] = patternize(Loc::lget('group_more'), $t);
						}
					}
					$row['g:pages'] = (!$u) ? '' : '<br />' . join('<br />', $u);
					$a[] = patternize($this->GROUP_ITEM, $row);
				}

			$row['groups'] = join('', $a);

			return patternize($this->ID_PATTERN, $row);
		}

		public function actionCheck($r) {
			$id = uri_frag($r, 0);
			if (!$id)
				throw new Exception('Author ID not specified!');

			$aa = $this->getAggregator(0);
			$d = $aa->get($id, '`id` as `0`');
			if (intval($d[0]) != $id)
				throw new Exception('Author not found!');

			require_once 'core_updates.php';
			$u = new AuthorWorker();
			$u->check($id);

			$ga = $this->getAggregator(1);

			$g = $ga->fetch(array('nocalc' => 1, 'order' => false, 'filter' => "author = $id and `link` <> '' and `link` not like '/%'", 'collumns' => 'id as `0`'));
			if ($g['total'])
				foreach ($g['data'] as $row)
					if (!$u->checkGroup(intval($row[0]))) {
						echo Loc::lget('halting') . '<br />';
						return;
					}

		}

		function actionAdd($r) {
			$error = array();
			if (post('action') == 'add') {
				$v = array();
				foreach ($this->EDIT_STRINGS as $key) {
					${$key} = str_replace(PHP_EOL, '<br />', trim(post($key)));
					$v[$key] = ${$key};
					if (array_search($key, $this->EDIT_REQUIRES) !== false)
							if (${$key} == '')
								$error[$key] = true;
				}

				if (preg_match('/(https?\:\/\/samlib\.ru)?(\/?(\w\/[^\/]+))/i', $link, $m)) {
					$link = str_replace(array('%', '\'', '"'), '', $m[3]);
					$a = $this->getAggregator();
					$d = $a->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`link` like '%$link%'", 'collumns' => '`id`'));
					if ($d['total']) {
						$row = array_pop($d['data']);
						$a_id = intval($row['id']);
						locate_to("/authors/id/$a_id");
					}
					$v['link'] = $link;
					$v['fio'] = $link;
				} else
					$error['link'] = true;

				$this->view->id = $id = post_int('id');
				if (!$error) {
					$aggregator = $this->getAggregator();
					$id = $id ? $aggregator->update($v, $id) : $aggregator->add($v);

					if ($id)
						locate_to('/' . $this->_name . ($this->kind ? '?kind=' . $this->kind : ''));
					else
						throw new Exception('Insertion failed o_O');
				}
			}
			$this->view->errors = $error;
			$this->view->renderTPL("{$this->_name}/add");
		}

		public function actionId($r) {
			$author = uri_frag($r, 0);
			View::addKey('rss-link', $author ? "?author=$author" : '');
			return parent::actionId($r);
		}

		public function actionChronology($r) {
			$author = uri_frag($r, 0);
			if (!$author)
				throw new Exception('Author ID not specified!');

			View::addKey('rss-link', "?author=$author");

			$a = $this->getAggregator(0);
			$data = $a->get($author, '`id`, `fio`');
			if ($data['id'] != $author)
				throw new Exception('Author not found o__O');

			$alink = " - <a href=\"/authors/id/{$data['id']}\">{$data['fio']}</a>";
			View::addKey('hint', $alink);

			$p1 = '
					<div class="title update dotted">
						<span class="head">
							{%inline}<a href="/pages?group={%group}" class="nowrap">{%group_title}</a>{%title}
						</span>
						<span class="head small break">
							<span class="delta {%color}"><b>{%delta}</b></span>
							<span class="link time">{%time}</span>
						</span>
					</div>
				';
			$p2 = ':
							<a href="/pages/version/{%id}" class="nowrap">{%title}</a>{%hint}';


			require_once 'core_page.php';
			$p = PagesAggregator::getInstance();
			require_once 'core_updates.php';
			$a = UpdatesAggregator::getInstance();
			require_once 'core_history.php';
			$h = HistoryAggregator::getInstance();

			$d = $a->getAuthorUpdates($author);
			$u = array();
			$c3 = 0;
			$nn = count($d['data']);

			$day = 60 * 60 * 24;
			$config = Config::read('INI', 'cms://config/config.ini');
			$offset = $config->get('main.time-offset');
			$offset = explode('=', $offset);
			$offset = $offset[0] * 60 * 60;
			$now = time() + $offset;

			$now = ($now - $now % $day) / $day;
			$timeslice = array();
			$pages = array();
			if ($d['total']) {
				$sizes = array();
				foreach ($d['data'] as &$row) {
					$pages[] = intval($row['id']);
					$time = intval($row['time']) + $offset;
					$time = ($time - $time % $day) / $day;
					if ($now - $time > 30) $time = $now - 31;
					$timeslice[$time][] = &$row;
					if ($row['kind'] == UPKIND_SIZE)
						$sizes[intval($row['id'])] = 0;
				}

				if ($sizes)
					$q = $p->get(array_keys($sizes), 'id, size');
					foreach ($q as $size)
						$sizes[intval($size['id'])] = intval($size['size']);

				$uid = User::get()->ID();
				$traces = array();
				if ($uid && $pages) {
					$f = $h->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` in (" . join(",", $pages) . ")", 'collumns' => '`page`, `trace`'));
					if ($f['total'])
						foreach ($f['data'] as &$row)
							$traces[intval($row['page'])] = intval($row['trace']);
				}


//		debug($sizes);
//		debug($timeslice);

				foreach ($timeslice as $time => $day) {
					$t = array();
					foreach ($day as &$row) {
						$c3++;
						$val = $row['value'];
//				if (!$val) continue;
						$row['time'] = date('d.m.Y H:i:s', intval($row['time']));
						$row['inline'] = (strpos($row['group_title'], '@') !== false) ? '<span class="inline-mark">@</span>' : '';
						$row['group_title'] = str_replace(array('@ ', '@'), '', $row['group_title']);
						$row['title'] = patternize($p2, $row);
						$pageid = intval($row['id']);
						$trace = ($uid && isset($traces[$pageid])) ? $traces[$pageid] : -1;
						$hint = $pageid ? PageUtils::traceMark($uid, $trace, $pageid, $row['author']) : '';
						$hint = '<span style="position: absolute; margin-left: 10px;">' . $hint . '</span>';
						$row['hint'] = $hint;
						$change = 0; // don't move, UPKIND_SIZE/ADD/DELETE depends on this
						switch ($row['kind']) {
						case UPKIND_GROUP:
							$row['delta'] = patternize('перенесено из <a href="/pages?group={%old_id}">{%old_title}</a>', $row);
							$row['color'] = 'blue';
							break;
						case UPKIND_DELETED_GROUP:
							$row['title'] = '';
							$row['delta'] = 'группа удалена';
							$row['color'] = 'red';
							break;
						case UPKIND_INLINE:
							$row['title'] = '';
							$row['delta'] = $val ? 'вложенная группа' : 'не вложенная группа';
							$row['color'] = 'blue';
							break;
						case UPKIND_SIZE:
							$change = $sizes[$row['id']] - $val;
						case UPKIND_ADDED:
						case UPKIND_DELETED:
							$added = $val > 0;
							if (!$change) {
								$row['delta'] = Loc::lget($added ? 'added' : 'deleted') . ' (' . fs(abs($val * 1024)) . ')';
								$row['color'] = $added ? 'green' : 'maroon';
							} else {
								$row['color'] = $added ? 'green' : 'red';
								$row['delta'] = ($added ? '+' : '-') . fs(abs($val * 1024));
							}
						default:
						}
						$t[] = patternize($p1, $row);
					}
					$e = array();
						$t[$i = count($t) - 1] = str_replace(' dotted', '', $t[$i]);
						$o = join('', $t);
						$e[] = "
								<div class=\"cnt-item\" style=\"overflow: hidden;\">
									{$o}
								</div>
								";

					$u[] = '<small><b>&nbsp; ' . daysAgo($now - $time) . '</b></small>' . join('', $e);
				}
			}

			if (!$u) {
				echo Loc::lget('unset');
				return;
			}

			$_total = $d['total'];
			$_from = ($page - 1) * $pagesize + 1;
			$_to = min($_total, $_from + $pagesize - 1);

			$c3 = $updatelist ? "$_from-$_to" : $c3;
			$c3 = $c3 . '/<a href="?action=updatelist">' . $d['total'] . '</a>';
			$u = join('', $u);
			echo $u;
		}
	}

	function daysAgo($delta) {
		switch (true) {
		case $delta ==  0: return 'сегодня';
		case $delta ==  1: return 'вчера';
		case $delta <= 30: return $delta . ' ' . aaxx($delta, 'д', array('ень', 'ня', 'ней')) . ' назад';
		case $delta >  30: return 'больше месяца назад';
		}
	}
