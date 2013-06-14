<?php
	require_once 'view.php';

	class StaticTemplate {
		static $i = array();
		var $tpls = null;
		var $cnts = array();
		var $config = array();
		var $savepath = '';

		static function get($static, $tpls = null) {
			if (!isset(self::$i[$static]))
				self::$i[$static] = new self($static, $tpls);

			return self::$i[$static];
		}

		private function __construct($static, $tpls) {
			$this->tpls = $tpls ? $tpls : array();
			$v = View::getViewsDir(false);
			$this->savepath = ROOT . "$v[0]/configs/$static.ini";
			$this->loadCFG();
		}

		function loadCFG() {
			return $this->config = ($c = @unserialize(@file_get_contents($this->savepath))) ? $c : array();
		}
		function saveCFG() {
			return @file_put_contents($this->savepath, serialize($this->config));
		}

		function getTpl($tpl) {
			if (isset($this->cnts[$tpl]))
				return $this->cnts[$tpl];

			$path = View::findTPL($this->tpls[$tpl], true); // current view loc path to template
			$c = $path ? @file_get_contents($path) : false;
			$this->cnts[$tpl] = $c;
			return $c;
		}

		function putTpl($tpl) {
			if (!isset($this->cnts[$tpl]))
				return false;

			$path = View::findTPL($this->tpls[$tpl], true); // current view loc path to template
			if (!$path)
				return false;

			return @file_put_contents($path, $this->cnts[$tpl]);
		}

		function save() {
			View::clearCache();
			$this->putTpl('static');
			$this->saveCFG();
		}

		function bakeStatic() {
			$cfg = $this->config;
			$this->cnts['static'] = $this->bakeArray('main', &$cfg);
		}

		function getResult() {
			return $this->cnts['static'];
		}

		function bakeArray($main, &$cfg) {
			$c = $this->getTpl($main);
//			echo htmlspecialchars($c);
//			echo "[::$main]<br/>";
//			debug2($cfg);

			if (!$c || ($c == '')) {
//				debug2($cfg);
				return array_shift($cfg);
			}

			while (preg_match('/\{%([^\}]+)\}/', $c, &$m)) {
				$key = $m[1];
				if (is_array($cfg[$key]))
					$con = $this->bakeArray($key, &$cfg[$key]);
				else
					$con = $cfg[$key];
//				echo ">$key => $con<br/>";

				if (!is_array($cfg[$key]) || !count($cfg[$key]))
					unset($cfg[$key]);

//				debug2($con);
				$c = preg_replace("/\{\%$key\}/i", $con, $c, 1);
			}

			return $c;
		}
	}

?>