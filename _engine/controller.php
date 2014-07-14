<?php

	class Controller {
		protected $_name   = "index";
		protected $view    = null;
		protected $request = null;
		protected $response= null;

		public function init($view, $req, $resp) {
			$this->view    = $view;
			$this->request = $req;
			$this->response= $resp;
			if ($view != null) {
				$front = FrontEnd::getInstance();
				$title = strip_tags($front->get('config')->get('site-title'));
				View::addKey('site', $title);
				View::addKey('title', $title);
				View::addKey('moder', '');
			}
			$view->ctl      = $this;
		}

		public function name() {
			return $this->_name;
		}

		function buildGrip($actions) {
			$a = array();
			$l = array();
			$p = array();
			if (count($actions))
				foreach ($actions as $action)
					if ($action != 'main')
						if (!(($i = intval($action)) || ($action == 'page'))) {
							$lshort = Loc::lget($locuid1 = 'titles.' . $action);
							$loc = ($lshort != $locuid1) ? $lshort : Loc::lget($locuid2 = 'titles.' . join('', $p) . $action);
							if (($loc != $locuid1) && ((!isset($locuid2)) || ($loc != $locuid2))) {
								$p[] = $action;
								$l[] = $loc;
								$a[] = '<a href="/' . join('/', $p) . '">' . $loc . '</a>';
							} else {
								if ($loc == $locuid2) {
									$l[] = '404 - Not Found';
									$a[] = '404 - Not Found';
								}
								break;
							}
						} else {
							if (($i == 404) && !count($p)) {
								$l[] = '404 - Not Found';
								$a[] = '404 - Not Found';
							}
							break;
						}
			if ($c = count($a)) {
				$a[$c - 1] = (strpos($l = $l[$c - 1], '.') !== false) ? '404 - Not Found' : '<b>'.$l.'</b>';
				$a = join(' &rarr; ', $a);
				$a = '<a href="/">' . Loc::lget('titles.main') . '</a> &rarr; ' . $a;
			} else
				$a = '';

			return $a;
		}

		public function action() {
			$r = $this->request->getList();
			$page = strtolower(trim(uri_frag($r, 0, '', 0)));
			$this->user = User::get();
			$this->userACL = intval($this->user->_get(User::COL_ACL));
			$this->userModer = $this->userACL >= ACL::ACL_MODER;

			if (USE_TIMELEECH) TimeLeech::addTimes('ctl::action' . $page . '()');

			$ta = "titles.{$this->_name}$page";
			View::addKey('root', $this->_name);
			View::addKey('title', strip_tags((($page != '') && ($t2 = Loc::lget($ta)) != $ta) ? $t2 : Loc::lget("titles.{$this->_name}")));
			View::addKey('grip', $this->buildGrip(array_merge(array($this->_name), $r)));
			View::addKey('rss-link', "");

			if ($page) {
				View::addKey('page', $this->_name . ($page != $this->_name ? '-' . $page : ''));
				$action = 'action' . ucfirst($page);

				if (method_exists($this, $action))
					return $this->$action(array_slice($r, 1));
			} else
				View::addKey('page', $this->_name);

			return $this->actionMain($r);
		}
		function ctrButton($caption, $link) {
			echo '<center>';
			$this->view->renderButton($caption, $link);
			echo '</center>';
		}
		public function actionMain($r) {
			$page = strtolower(uri_frag($r, 0, $this->_name, 0));
			if ($page == $this->_name)
				return $this->view->renderTPL($this->_name);

			$acl = User::ACL();
			$loc = Loc::Locale();
			$fe = FrontEnd::getInstance();
			$r  = $fe->viewroot[0] . '/';
			$static = "$r{$loc}/static/{$page}.tpl";
			if (!file_exists($static))
				return $this->action404($r);


			View::$keys['title'] = strip_tags(Loc::lget('titles.' . $page));
			if ($this->userModer) {
				$a = array('page' => $page, 'edit' => Loc::lget('edit'), 'delete' => Loc::lget('delete'));
				View::$keys['smoder'] = patternize('<span class="pull_right">[<a href="/statics/edit/{%page}">{%edit}</a> | <a href="/statics/delete/{%page}">{%delete}</a>]</span>', $a);
			}
//			View::$keys['content'] =
			echo @file_get_contents($static);
//			$this->view->renderTPL('ustatic');
		}

		function action404($r) {
			View::$keys['title'] = '404 - Not Found';
			$this->view->renderTPL('http/404');
		}
	}

	class AdminViewController extends Controller {
		public function action() {
			$fe = FrontEnd::getInstance();
			foreach ($fe->viewroot as $idx => $root)
				$fe->viewroot[$idx] .= '/admin';
			parent::action();
		}
	}
