<?php
	require_once 'request.php';

	class Route {
		var $from = null;
		var $to   = null;

		function Route($from, $to) {
			$this->from = is_array($from) ? $from : array($from);
			$this->to   = is_array($to)   ? $to   : array($to);
		}
	}

	class Router {
		var $INDEX_CTLNAME = 'index';

		var $routes = array();

		public function Router() {
			$this->INDEX_CTLNAME = FrontEnd::getInstance()->get('config')->get('main-controller');
			$this->addRoute(new Route(array(), $this->INDEX_CTLNAME));
		}

		function addRoute(Route $route) {
			$this->routes[] = $route;
		}
		function addFirst(Route $route) {
			$this->routes = array($route) + $this->routes;
		}

		function getDefaultRoot($rest = array()) {
			return $this->routeURI(array_merge(array($this->INDEX_CTLNAME), $rest));
		}

		function getErrorController() {
			return $this->getRoute('error');
		}

		function getController($params) {
			$name  = isset($params['controller']) ? $params['controller'] : $this->INDEX_CTLNAME;
			$route = ($route = $this->getRoute($name)) === null ? $this->getDefaultRoot() : $route;
			return $route;
		}

		public function routeURI(array $fromURI) {
			if (count($fromURI) == 0)
				return $this->getDefaultRoot();

			$def = array();
			foreach ($this->routes as $route) {
				$uri   = $fromURI;
				$from  = $route->from;
				$match = true;
				if (count($from) == 0) {
					$def = $route->to;
				} else {
					foreach ($from as $part) {
						$upart = array_shift($uri);
						if (strtolower($part) != $upart) {
							$match = false;
							break;
						}
					}
					if ($match) {
						return array_merge($route->to, $uri);
					}
				}
			}

			return $fromURI[0] != $this->INDEX_CTLNAME ? array_merge($def, $fromURI) : $fromURI;
		}

		public function routeRequest(Request $request) {
			$list   = $request->getList();
			$routed = $this->routeURI($list);
/*      if (is_diff($list, $routed)) {
				require_once 'uri.php';
				$uri = new URI(array('path' => implode('/', $routed), 'args' => $request->getArgs()));
				header('Location: ' . (string)$uri);
			}*/

			$request->setActions(array_shift($routed));
			$request->setList($routed, true);
		}
	}

	function is_diff(array $a1, array $a2) {
		$i = 0;
		$c = count($a2);
		foreach ($a1 as $v1)
			if (($i == $c) || ($v1 != $a2[$i++]))
				return true;
		return $i < $c;
	}

?>
