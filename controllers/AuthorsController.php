<?php
	require_once 'core_bridge.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';
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
							<span class="pull_right">[<a href="/pages?author={%id}">{%pages}</a>|<a href="/{%root}/check/{%id}">{%checkupdates}</a>]</span>
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
					$a[] = "<a href=\"/authors?by_name=$c\" class=\"filter$color\">$c</a> <small style=\"font-weight: normal; color: #888;\">($o)</small>";
				}
			$this->view->fio_filter = join(', ', $a);

			parent::actionPage($r);
		}

		public function makeIDItem(&$aggregator, &$row) {
			View::addKey('title', $row['fio']);
			html_escape($row, array('fio', 'link'));
			$row['pages'] = Loc::lget('pages');
			$row['checkupdates'] = Loc::lget('checkupdates');

			$ga = $this->getAggregator(1);
			$g = $ga->fetch(array('nocalc' => true, 'order' => 'replace(`title`, "@", ""), `time`', 'desc' => 1, 'filter' => '`author` = ' . $row['id'], 'collumns' => '`id`, `title`, `link`, `description`'));
			$a = array();
			$pa = $this->getAggregator(3);
			$ha = $this->getAggregator(4);
			$uid = $this->user->ID();
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
							$rowp['mark'] = $pa->traceMark($uid, $trace, $id, $row['id']);
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
	}
?>