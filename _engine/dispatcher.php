<?php
	require_once 'request.php';
	require_once 'response.php';
	require_once 'controller.php';
	require_once 'frontend.php';
	require_once 'acl.php';
	require_once 'localization.php';

	class Dispatcher {
		private $_req = null;
		private $_res = null;
		private $_view= null;

		public function Dispatcher(Request $request, Response $response) {
			$this->_req = $request;
			$this->_res = $response;
		}

		public function getRequest() {
			return $this->_req;
		}
		public function getResponse() {
			return $this->_res;
		}

		public function dispatch($view) {
TimeLeech::addTimes('before dispatch');
			$this->_view = $view;
			ob_start();
			try {
				$front   = FrontEnd::getInstance();
				$ctlroot = $front->getControllerRoot();
				require_once 'user.php';
TimeLeech::addTimes('before acl');
				$acl = User::get()->ACL();
TimeLeech::addTimes('after acl');
				do {
					$act = $this->_req->shiftAction();
					if (!$act) break;
					$root = array_merge(array($act), $this->_req->getList());
					if (!ACL::allowed($acl, $root)) {
						$root = join('/', $root);
						$params = $_GET;
						if (count($params)) {
							$p = array();
							foreach ($params as $key => $param)
								$p[] = $key . '=' . $param;
							$root .= '?' . join('&', $p);
						}
						if (!$acl) {
							header('Location: http://' . $_SERVER['HTTP_HOST'] . '/user/login?url=' . urlencode($root));
							die();
						}
						throw new Exception('err_acl');
					}

					$ctl = ucfirst(strtolower($act)) . 'Controller';
TimeLeech::addTimes('before loadClass');
					require_once 'loader.php';
					if (!Loader::loadClass($ctl, $ctlroot)) {
						$mainctl = $front->get('config')->get('main-controller');
						if (strtolower($act) == $mainctl)
							throw new Exception('Fatal error: can\'t load main controller', E_ERROR);
						$this->_req->setActions(array_merge(array($mainctl, $act), $this->_req->getActions()));
						$this->_req->setList($this->_req->getActions());
						continue;
					}
TimeLeech::addTimes('after loadClass');

TimeLeech::addTimes('before controller work');
					$controller = new $ctl();
					$controller->init($this->_view, $this->_req, $this->_res);
					$controller->action($this);
TimeLeech::addTimes('after controller work');
					break;
				} while (true);
			} catch (Exception $e) {
				View::addKey('title', strip_tags(Loc::lget('msg_err')));
				View::addKey('site', strip_tags(FrontEnd::getInstance()->get('config')->get('site-title')));
				$view->errors = array(wrapExceptionTrace($e));
				$view->renderTPL('error');
			};
TimeLeech::addTimes('after dispatch');
			$this->_res->set(ob_get_contents());
			ob_end_clean();
		}
	}
?>