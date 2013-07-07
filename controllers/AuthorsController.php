<?php
	require_once 'core_bridge.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';

	require_once 'AggregatorController.php';

	define('PAGES_PER_GROUP', 3);

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
							<a href="/pages?group={%g:id}">{%g:title}</a>
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

			$ga = $this->getAggregator(1);
			$g = $ga->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => '`author` = ' . $row['id'], 'collumns' => '`id`, `title`, `link`, `description`'));
			$a = array();
			$pa = $this->getAggregator(3);
			if ($g['total'])
				foreach ($g['data'] as $rowg) {
					$row['g:id'] = ($group_id = intval($rowg['id']));
					$row['g:link'] = preg_match('/^[\/\\\]/', $rowg['link']) ? $rowg['link'] : "/{$row['link']}/{$rowg['link']}";
					$row['g:title'] = $rowg['title'];
					$row['g:desc'] = $rowg['description'];
					$d = $pa->fetch(array('desc' => 0, 'filter' => "`group` = $group_id limit 3", 'collumns' => '`id`, `title`'));
					$t = $d['total'];
					$u = array();
					if ($t) {
						foreach ($d['data'] as $rowp) {
							$u[] = patternize('&rarr; <a href="/pages/version/{%id}">{%title}</a>', $rowp);
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