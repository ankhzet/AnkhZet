<?php

	function escapeField($f) {
		return htmlspecialchars(stripslashes(strip_tags($_POST[$f])), ENT_QUOTES);
	}

	class UserController extends Controller {
		protected $_name = 'user';

		public function actionEdit($r) {
			if (User::ACL() == ACL::ACL_GUEST) locate_to('/user/login');
			$u = User::get();
			$fields = array('name', 'pass', 'pass2');
			if (post('action') == 'edit') {
				foreach ($fields as $field) $this->view->editdata[$field] = escapeField($field);
				$this->view->editstate = User::update($this->view->editdata);

				$this->view->renderTPL('user/profile');
				if (!$this->view->editstate) {
					echo '<div style="clear: both">';
					$this->view->renderMessage(Loc::lget('saved'), View::MSG_INFO);
					echo '</div>';
				}
			} else {
				foreach ($fields as $field) $this->view->editdata[$field] = $u->_get($field);
				$this->view->renderTPL('user/profile');
			}
		}

		public function actionRegistration($r) {
			$params = array('name', 'email', 'pass', 'pass2', 'captcha');
			$this->view->regdata = array();
			foreach ($params as $param)
				$this->view->regdata[$param] = addslashes(post($param));

			if (post('action') == 'registration') {
				$this->view->regstate = User::register($this->view->regdata);

				if (!count($this->view->regstate)) {
					require_once "session.php";
					require_once "dbengine.php";
					$s = Ses::get(SAM::SAM_COOKIES);
					$l = $this->view->regdata['email'];
					$d = msqlDB::o();
					$e = $d->select('users', '`login` = \'' . $l . '\'');
					$r = $d->fetchrows($e);
					$ok = count($r) == 1;

					if (!$ok)
						return $this->view->renderTPL('user/login');

					$uid = $uid = intval($r[0]['id']);
					$s->gen($uid);
					$s->write(SAM::SAM_COOKIES, false);
					$location = uri_frag($_REQUEST, 'url', '/user', 0);
					if (!preg_match('/^\//', $location)) $location = "/$location";
					locate_to($location);
				}
			}
			$this->view->renderTPL('user/registration');
		}

		public function actionRestore($r) {
			$tpl = 'restorepass';
			if (post('action') == 'restore') {
				sleep(1);
				$l['email'] = addslashes(post('email'));
				$this->view->restoredata = User::checkRegData($l);
				if ($this->view->restoredata[User::FLD_LOGIN] != User::ERR_LOGIN) {
					$u = new User();
					$u->_set(User::COL_LOGIN, $l['email'], true);
					if (!intval($u->_get(User::COL_ID)))
						$this->view->restoredata['unknown'] = 1;
					else {
						$newpass = User::genPassword(10 + rand(0, 5));
						if (User::passReminder($l['email'], $newpass, true)) {
							$tpl = 'login';
							$this->view->restore = 1;
						}
					}
				}
			}
			$this->view->renderTPL("user/$tpl");
		}

		public function actionMain($r) {
			$u = User::get();
			$uid = intval($u->_get(User::COL_ID));
			$id = uri_frag($r, 0);
			if ($id && ($id != $uid)) {
				if ($u->_get(User::COL_ACL) == ACL::ACL_GUEST) locate_to('/user/login');
				$u = User::get($id);
				if ($id != intval($u->_get(User::COL_ID)))
					throw new Exception('Нет пользователя с таким ID');

				$fields = array('name');
				foreach ($fields as $field) $this->view->editdata[$field] = $u->_get($field);
				$this->view->renderTPL('user/profile_short');
			} else
				return $this->actionEdit($r);
		}

		public function actionLogout($r) {
			require_once "session.php";
			$s = Ses::get(SAM::SAM_COOKIES);
			Loc::Locale();
			$s->read(null, null, Loc::$locid);
			$s->write(SAM::SAM_COOKIES);
		}

		public function actionLogin($r) {
			require_once "session.php";
			require_once "dbengine.php";
			$s = Ses::get(SAM::SAM_COOKIES);
			$l = post('login');
			$p = post('pass');
			if ($l && $p) {
				$d = msqlDB::o();
//				echo "[" . md5($p . User::PASS_SALT) . "]<br>";
				$e = $d->select('users', '`login` = \'' . $l . '\' and `password` = \'' . md5($p . User::PASS_SALT) . '\'');
				$r = $d->fetchrows($e);
				$ok = count($r) == 1;
				if (!$ok) {
					$this->view->errors = true;
					$this->view->renderTPL('user/login');
					sleep(1);
					return;
				}

				$uid = $uid = intval($r[0]['id']);
				$s->gen($uid);
				$s->write(SAM::SAM_COOKIES, false);
				$location = uri_frag($_REQUEST, 'url', '', 0);
				locate_to(preg_match('-^/-', $location) ? $location : "/$location");
				return;
			}
			$this->view->renderTPL('user/login');
		}


	}
?>