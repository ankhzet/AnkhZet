<?php
	require_once 'core_statics.php';
	require_once 'AggregatorController.php';

	class StaticsController extends AggregatorController {
		protected $_name = 'statics';

		var $ID_PATTERN = '<div class="block"><div class="header"><div>{%title}{%moder}</div></div><div class="cont">{%link}</div><div class="footer"></div></div>';
		var $MODER_EDIT = '<span class="pull_right">[<a href="/statics/edit/{%id}">{%edit}</a> | <a href="/statics/delete/{%id}">{%delete}</a>]</span>';
		var $LIST_ITEM  = '<div class="static">
						<b>{%title}</b>&nbsp;<a href="/{%link}">{%root}/{%link}</a> {%moder}
					</div>
';

		var $USE_UL_WRAPPER = false;
		var $LIST_JOINER = "<div class=\"separator\"></div>\r\n";

		var $EDIT_STRINGS = array('title', 'link');
		var $EDIT_REQUIRES= array('title', 'link');

		function getAggregator() {
			return StaticsAggregator::getInstance();
		}

		public function makeItem(&$aggregator, &$row) {
			$row[root] = 'http://' . $_SERVER['HTTP_HOST'];
			return patternize($this->LIST_ITEM, $row);
		}

		function actionAdd($r) {
			$id = post_int('id');
			$error = array();
			if (post('action') == 'add') {
				$v = array();
				foreach ($this->EDIT_STRINGS as $key) {
					$v[$key] = addslashes(trim(post($key)));
					if (array_search($key, $this->EDIT_REQUIRES) !== false)
							if ($v[$key] == '')
								$error[$key] = true;
				}

				$v['link'] = str_replace(' ', '-', $v['link']);
				if (!preg_match('/^[a-z0-9\_\-\@\#\*\\;\$\%\(\)]+$/i', $v['link']))
					$error['link'] = true;

				if (!count($error)) {
					$pa = $this->getAggregator();
					$d = $pa->fetch(array('pagesize' => 1, 'filter' => "`link` = '{$v['link']}'", 'collumns' => '`id`'));
					$rid = intval($d['data'][0]['id']);
					if ($id)
						if ($rid && ($rid != $id))
							throw new Exception("Ссылка уже <a href=\"/statics/edit/$rid\">используется</a>");

					$safe_link = stripslashes(stripslashes(str_replace(array('=', PHP_EOL), array('&#61;', ''), $v['title'])));

					$statics = SUB_VIEWS . '/ru/static/';
					if ($id) {
						$d = $pa->get($id);
						$pa->delete($id);
						$contents = @file_get_contents("$statics{$d['link']}.tpl");
						@unlink("$statics{$d['link']}.tpl");
						Loc::$config->set("ru.titles.{$v['link']}", '');
					}

					if (($id = $pa->add($v))) {
						Loc::$config->set('ru.titles.' . $v['link'], $safe_link);
						Loc::$config->save();
						$filename = "$statics{$v[link]}.tpl";
						if (!file_exists($filename))
							file_put_contents($filename, $contents ? $contents : '&lt;empty&gt;');

						locate_to("/templates/ru/{$v['link']}?back=statics");
					} else
						throw new Exception('Insertion failed! o_O');
				}
			}

			$this->view->errors = $error;
			$this->view->renderTPL('statics/add');
		}

		function actionDelete($r) {
			$id = uri_frag($r, 0);
			if (!$id) {
				$name = $r[0];
				$a = $this->getAggregator();
				$d = $a->fetch(array('pagesize' => 1, 'filter' => "`link` = '$name'", 'collumns' => '`id`'));
				$id = intval($d['data'][0]['id']);
			}

			if ($id) {
					$pa = $this->getAggregator();
					$d = $pa->get($id);
					$pa->delete($id);
					@unlink(SUB_VIEWS . "/ru/static/{$d[link]}.tpl");
					locate_to("/{$this->_name}/page/" . uri_frag($_REQUEST, 'page', 1));
			} else
				throw new Exception('Entity ID not specified');
		}

		function actionEdit($r) {
			if (('' . uri_frag($r, 0)) == $r[0])
				return parent::actionEdit($r);

			$name = $r[0];
			$a = $this->getAggregator();
			$d = $a->fetch(array('pagesize' => 1, 'filter' => "`link` = '$name'", 'collumns' => '`id`'));
			$id = intval($d['data'][0]['id']);
			if ($id)
				return parent::actionEdit(array($id));
			else
				throw new Exception('Entry not found!');
		}
	}