<?php

	define('CTL_ROOT', ROOT . '/controllers');
	define('VIEWS_ROOT', ROOT . '/views');
	define('SUB_CTLS', SUB_DOMEN . '/controllers');
	define('SUB_VIEWS', SUB_DOMEN . '/views');

	class FrontEnd {
		private static $_instance = null;

		private function __construct() {
			require_once 'common.php';
		}

		public static function getInstance() {
			if (!isset(self::$_instance)) {
				self::$_instance = new self();
				self::$_instance->init();
			};

			return self::$_instance;
		}

		public function get($param) {
			return $this->{$param};
		}

		public function set($param, $value) {
			$this->{$param} = $value;
			return $value;
		}

		public function route() {
			$this->get('router')->routeRequest($this->request);
		}

		public function dispatch($render) {
			if ($render) {
				require_once 'view.php';
				$view = $this->set('view', new View());
			}

			$this->get('dispatcher')->dispatch($view);

			$r = $this->get('response');
			if ($render)
				$view->render($r);
			else
				return $r;
		}

		public function init() {
//TimeLeech::addTimes('initDirectories ->');
			$this->initDirectories();
//TimeLeech::addTimes('readConfig ->');
			$this->readConfig();
//TimeLeech::addTimes('initRequest ->');
			$this->initRequest();
//TimeLeech::addTimes('initResponse ->');
			$this->initResponse();
//TimeLeech::addTimes('initRouter ->');
			$this->initRouter();
//TimeLeech::addTimes('initDispatcher ->');
			$this->initDispatcher();
//TimeLeech::addTimes('Frontend::init <-');
		}

		public function initDirectories() {
			$this->ctlroot  = array(SUB_CTLS, CTL_ROOT);
			$this->viewroot = array(SUB_VIEWS, VIEWS_ROOT);
			$include = explode(PATH_SEPARATOR, get_include_path());
			$include = array_unique(array_merge(array(ENGINE_ROOT, SUB_MODELS, SUB_VIEWS, MODELS_ROOT, VIEWS_ROOT), $include));
			set_include_path(join(PATH_SEPARATOR, $include));
		}

		public function readConfig() {
			require_once 'config.php';
			require_once 'acl.php';
			$config = $this->set('config', Config::read('INI', 'cms://config/config.ini'));

			ACL::readConfig($config);
			return $config;
		}

		public function initRequest() {
			require_once 'request.php';
			return $this->set('request', new Request($this->getRequestURI()));
		}

		public function initResponse() {
			require_once 'response.php';
			return $this->set('response', new Response());
		}

		public function initRouter() {
			require_once 'router.php';
			$routes = $this->get('config')->get('routing');

			$router = $this->set('router', new Router());
			if (is_array($routes))
				foreach ($routes as $from => $to)
					$router->addRoute(new Route(explode('/', $from), explode('/', $to)));
			return $router;
		}

		public function initDispatcher() {
			require_once 'dispatcher.php';
			return $this->set('dispatcher', new Dispatcher($this->request, $this->response));
		}

		public static function getRequestURI() {
			return $_SERVER['REQUEST_URI'];
		}

		public function getRequest() {
			return $this->get('request');
		}

		public function getResponse() {
			return $this->get('response');
		}

		public function getRouter() {
			return $this->get('router');
		}

		public function getDispatcher() {
			return $this->get('dispatcher');
		}

		public function getControllerRoot() {
			return $this->ctlroot;
		}
		public function getViewRoot() {
			return $this->viewroot;
		}

	}
?>