<?php

//	require_once 'aggregator.php';

// TIP: non-abstract aggregator must be included in subclass script,
//      i.e. NewsController.php must inherit from AggregatorController
//      and include news_aggregator.php and AggregatorController.php

	class AggregatorController extends Controller {
		protected $_name = 'abstract';

		var $ID_PATTERN = '<b>ID: {%id}</b><br />';
		var $MODER_EDIT = '<span class="pull_right">[<a href="/{%root}/edit/{%id}">{%edit}</a> | <a href="/{%root}/delete/{%id}">{%delete}</a>]</span>';
		var $LIST_ITEM  = '<li>ID: <a href="/{%root}/id/{%id}">{%id}</a><br />{%time}<br />{%moder}</li>';
		var $ADD_MODER  = 1;
		var $ALWAYS_ADD = 0;
		var $link = '';

		var $EDIT_STRINGS = array('title', 'content');
		var $EDIT_FLOATS  = array();
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array('title', 'content');

		var $USE_UL_WRAPPER = true;
		var $LIST_JOINER = PHP_EOL;

		function getAggregator() {
//			return Aggregator::getInstance();
			throw new Exception('Abstract aggregator usage!');
		}

		function actionInit($r) {
			if (User::ACL() >= ACL::ACL_ADMINS) {
				$aggregator = $this->getAggregator(uri_frag($r, 0));
				if ($aggregator->init())
					$this->view->renderMessage('Initialization succesfull.', View::MSG_INFO);
				else
					$this->view->renderMessage('Initialization failed!', View::MSG_ERROR);
			} else
				$this->actionPage($r);
		}

		public function actionMain($r) {
			return $this->actionPage(array());
		}

		public function makeIDItem(&$aggregator, &$row) {
			return patternize($this->ID_PATTERN, $row);
		}

		public function makeItem(&$aggregator, &$row) {
			return patternize($this->LIST_ITEM, $row);
		}

		public function noEntry(&$aggregator, $id) {
			throw new Exception('Entry not found');
		}

		public function actionId($r) {
			$id = uri_frag($r, 0);
			if ($id) {
				$aggregator = $this->getAggregator();
				$entry = $aggregator->get($id);
				if (!($entry && count($entry)))
					$entry = $this->noEntry($aggregator, $id);

				$entry['utime'] = ($utime = intval($entry['time']));
				$entry['time'] = date('d.m.Y', $utime);
				$entry['root'] = $this->_name;

				if ($this->userModer) {
					$entry['page'] = 1;
					$entry['edit'] = Loc::lget('edit');
					$entry['delete'] = Loc::lget('delete');
					View::addKey('moder', $entry['moder'] = patternize($this->MODER_EDIT, $entry));
				} else
					$entry['moder'] = '';

				echo $this->makeIDItem($aggregator, $entry);
			} else
				throw new Exception('Entry ID not specified');
		}

		function prepareFetch($filters) {
			return $filters;
		}

		function actionPage($r) {
			if ($this->userModer && $this->ADD_MODER || $this->ALWAYS_ADD)
				View::addKey('moder', '<span class="pull_right">[<a href="/' . $this->_name . '/add">' . Loc::lget('add') . '</a>]</span>');

			$aggregator = $this->getAggregator();
			$this->page = $page = uri_frag($r, 0, 1);
			$params = array('page' => $page - 1, 'pagesize' => $aggregator->FETCH_PAGE, 'desc' => true);
			if (isset($this->query)) $params['filter'] = $this->query;
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
				$moder = $this->userModer;
				$i = 0;
				foreach($this->data['data'] as &$row) {
					$row['root'] = $this->_name;
					$row['utime'] = ($utime = intval($row['time']));
					$row['time'] = date('d.m.Y', $utime);
					$row['page'] = $page;
					if ($moder) {
						$row['edit'] = Loc::lget('edit');
						$row['delete'] = Loc::lget('delete');
						$row['moder'] = patternize($this->MODER_EDIT, $row);
					} else
						$row['moder'] = '';

					$n[] = $this->makeItem($aggregator, $row);
				}
				$n = join($this->LIST_JOINER, $n);
			}

			$p = ($last > 1) ? $aggregator->generatePageList($page, $last, $this->_name . '/', $this->link) : '';
			$this->view->pages = "<ul class=\"pages\">\n$p</ul>\n";

			$this->view->data = $n ? ($this->USE_UL_WRAPPER ? "<ul class=\"{$this->_name}\">\n$n\n</ul>" : $n) : Loc::lget($this->_name . '_nodata');
			$this->view->renderTPL("{$this->_name}/index");
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

				foreach ($this->EDIT_FLOATS as $key) {
					${$key} = (float)(trim(post($key)));
					$v[$key] = ${$key};
					if (array_search($key, $this->EDIT_REQUIRES) !== false)
							if (${$key} <= 0)
								$error[$key] = true;
				}

				foreach ($this->EDIT_FILES as $key) {
					${$key} = trim(post($key));
					$v[$key] = ${$key};
					if (array_search($key, $this->EDIT_REQUIRES) !== false)
							if (${$key} == '')
								$error[$key] = true;
				}

				$this->view->id = $id = post_int('id');
				if (!count($error)) {
					$aggregator = $this->getAggregator();

					if ($id) {
						$d = $aggregator->get($id, '`time`' . (count($this->EDIT_FILES) ? ', `' . join('`, `', $this->EDIT_FILES) . '`' : ''));
						$v['time'] = intval($d['time']);
						foreach ($this->EDIT_FILES as $key)
							if ($v[$key]) { // file transmitted
								$file = basename($v[$key]);
								$path = SUB_DOMEN . '/data/' . $this->_name . '/';
								$temp = SUB_DOMEN . $v[$key];
								$new = $path . $file;
								if ($new == $temp) continue; // this is the same file


								if ($d[$key] && ($file != $d[$key])) // old existing and new isn't the same file
									@unlink($path . $d[$key]);
							}
					}

					foreach ($this->EDIT_FILES as $key)
						if ($v[$key]) { // file transmitted
							$file = basename($v[$key]);
							$path = SUB_DOMEN . "/data/{$this->_name}/";
							$temp = ($v[$key] == $file) ? $path . $file : SUB_DOMEN . $v[$key];
							$new = $path . $file;
							if ($new == $temp) {
								unset($v[$key]);
								continue; // this is the same file
							}

							$i = pathinfo($file);
							$e = $i['extension'];
							$e = $e ? ".{$e}" : '';
							$base = basename($file, $e);
							$i = 0;
							while (is_file($path . $file)) { // same filename as new existing
								$i++;
								$file = "{$base}_{$i}$e";
							}
							@rename(SUB_DOMEN . $v[$key], $path . $file);
							$v[$key] = $file;
						}

					$id = $id ? $aggregator->update($v, $id) : $aggregator->add($v);

					if ($id) {
						$kind = $this->kind ? "?kind={$this->kind}" : '';
						locate_to("/{$this->_name}$kind");
					} else
						throw new Exception('Insertion failed o_O');
				}
			}
			$this->view->errors = $error;
			$this->view->renderTPL($this->_name . '/add');
		}

		public function actionEdit($r) {
			$id = uri_frag($r, 0);
			$this->view->id = $id;
			if ($id) {
				$aggregator = $this->getAggregator();
				$entry = $aggregator->get($id);

				foreach (array_merge($this->EDIT_STRINGS, $this->EDIT_FLOATS, $this->EDIT_FILES) as $key) {
					${$key} = str_replace('<br />', PHP_EOL, trim($entry[$key]));
					$_REQUEST[$key] = ${$key};
				}
			} else
				throw new Exception('Entry ID not specified');

			$this->view->renderTPL("{$this->_name}/add");
			return $id;
		}

		public function actionDelete($r) {
			$id = uri_frag($r, 0);
			if ($id) {
				$aggregator = $this->getAggregator();
				if (count($this->EDIT_FILES)) {
					$d = $aggregator->get($id, '`' . join('`, `', $this->EDIT_FILES) . '`');
					$path = SUB_DOMEN . "/data/{$this->_name}/";
					foreach ($this->EDIT_FILES as $key)
						if ($d[$key])
							@unlink($path . $d[$key]);
				}

				$s = $aggregator->delete($id);
				if ($s) {
					if ($ret = post('return'))
						locate_to("/$ret");
					else {
						$page = uri_frag($_REQUEST, 'page', 1, 1);
						locate_to("/{$this->_name}/page/$page");
					}
				} else
					throw new Exception('Deletion failed o_O');
			} else
				throw new Exception('Entry ID not specified');
		}

	}
?>