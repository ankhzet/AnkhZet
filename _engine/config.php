<?php
	require_once 'toolkit.php';

	class Config {
		private static $_configs = array();

		protected $_data   = null;
		protected $_source = null;
		public static $readtime = 0.0;

		public function __construct($source) {
			$this->initFrom($source);
		}

		public static function read($class, $source) {
			if (strpos($source, 'cms://') === false)
				$source = "cms://config/$source";

			if (isset(self::$_configs[$source]))
				return self::$_configs[$source];
			else {
				$class = 'Config_' . $class;
				$config = new $class($source);
				$start = getutime();
				$data = $config->doRead();
				$time = getutime() - $start;
				self::$readtime += $time;
//				TimeLeech::addTimes('config [' . $source . '] readed for ' . self::$readtime);
				return $data;
			}
		}

		public function initFrom($source) {
			self::$_configs[$source] = $this;

			$this->_source = $source;
			$this->_data   = array('main' => array());
		}

		public function get($param) {
			if (!is_array($param)) $param = explode('.', $param);

			$data = &$this->_data;
			foreach ($param as $section) {
				if (is_array($data))
					if (array_key_exists($s = strtolower($section), $data))
						$data = &$data[$s];
					else {
						unset($data);
						break;
					}
				else
					break;
			}

			if ((!isset($data)) && ($param[0] != 'main'))
				return $this->get(array_merge(array('main'), $param));
			else
				return isset($data) ? $data : null;
		}

		public function set($param, $value) {
			if (!is_array($param)) $param = explode('.', $param);
//			debug($param, $value);
			$data = &$this->_data;
			foreach ($param as $section) {
//				echo '[' . $section . ']';
				if (!isset($data[strtolower($section)]))
					$data[strtolower($section)] = '';
					$data = &$data[strtolower($section)];
			}
			$data = $value;
//			echo '<br />';
			return $data;
		}

		public function doRead() {
			return $this;
		}

		public function doSave() {
			return $this;
		}

		public function save() {
			return $this->doSave();
		}
	}

	class Config_INI extends Config {
		public function initFrom($source) {
			parent::initFrom($source);
		}

		public function doRead() {
			$lines = file($this->_source);
			if (!$lines) $lines = array();
			$data = &$this->_data['main'];
			foreach ($lines as $line) {
				$line = trim($line);
				if ($line == '') continue;
				if (preg_match('/^\[((([\w\d_]+)(\.([\w\d_]+))*))\]$/', $line, $found)) {
					$section = strtolower($found[1]);
					$data = &$this->_data;
					foreach (explode('.', $section) as $section) {
						if (!isset($data[$section]))
							$data[$section] = array();
						else if (!is_array($data[$section]))
							$data[$section] = array($data[$section]);
						$data = &$data[$section];
					}
					continue;
				}
				if (preg_match('/^([^\=]+)\=(.*)$/', $line, $declaration)) {
					$param = strtolower(trim($declaration[1]));
					$value = trim($declaration[2]);
					if (isset($data[$param])) {
						if (is_array($data[$param]))
							$data[$param][] = $value;
						else
							$data[$param] = array($data[$param], $value);
					} else
						$data[$param] = $value;
					continue;
				}

			}
//			debug2($this->_data, 'doread');
			return $this;
		}

		public function doSave() {
			$data = &$this->_data;
			$l = array();

//			echo '<pre>';
			cfg_add_recurse($l, array(), '', $data);
//			debug2($l, 'list');
//			die('asdadasdasdadadas');
			$contents = PHP_EOL . join(PHP_EOL, $l);
			$f = @fopen($this->_source, 'w');
			if ($f) {
				flock($f, LOCK_EX);
				try {
					fputs($f, $contents);
				} catch (Exception $e) {
					$e->printDump();
				}
				flock($f, LOCK_UN);
				fclose($f);
			} else
				die('Can\'t save config!');

			return $this;
		}

	}

	function cfg_add_recurse(&$array, $parent, $param, $value) {
		$a = array();
		$p = array();
		foreach ($value as $par => $sub)
			if (($par = trim($par)) != '')
				if (is_array($sub))
					$a[$par] = $sub;
				else
					if (($sub = trim($sub)) != '')
						$p[$par] = $sub;

//				debug2($a, $param . '.a');
//				debug2($p, $param . '.p');

		$root = $param ? array_merge($parent, array($param)) : $parent;

		foreach ($a as $par => $sub)
			if (count($pp = cfg_add_recurse($array, $root, $par, $sub))) {
				$array[] = '[' . join('.', array_merge($root, array($par))) . ']';
				foreach ($pp as $par => $sub)
					$array[] = $par . ' = ' . $sub;
			}
		return $p;
	}
