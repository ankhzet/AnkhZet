<?php
	require_once 'common.php';
	require_once 'frontend.php';

	function _PTT($matches) {
		$key = strtolower($matches[1]);
		$par = strtolower($matches[3]);
		$fn  = strtolower($matches[5]);
		if (!$key)
			return '{%' . $key . ':' . $par . ($fn ? '#' . $fn : '') . '%}';

		switch ($key) {
		case 'tpl':
			$l = isset(View::$pctpl[$par]) || View::findTPL($par, true);
			if ($l) {
				View::regTpl($par);

				return USE_TIMELEECH
					? "<?TimeLeech::addTimes('before {$par}_$fn');echo {$par}_$fn(View::\$instance);TimeLeech::addTimes('after {$par}_$fn');?>"
					: "<?={$par}_$fn(View::\$instance)?>\n";
			} else
				return '<!-- [' . $par . '] template not found -->';
		case 'patt':
			$l = View::findTPL($par, true);
			if ($l) {
				$e = file_get_contents($l);
				foreach (explode(',', $fn) as $function)
					switch ($function) {
					case 'escape':
						$e = addslashes($e);
						break;
					case 'unhtml':
						$e = htmlspecialchars(strip_tags($e));
						break;
					case 'camelcase':
						$e = ucfirst($e);
						break;
					case 'upper':
						$e = mb_strtoupper($e);
						break;
					default:
					}
				return $e;
			} else
				return '<!-- [' . $par . '] pattern not found -->';
		default:
			$e = 'View::$keys[\'' . $key . '\']';
			foreach (explode(',', $fn) as $function)
				switch ($function) {
				case 'escape':
					$e = 'addslashes(' . $e . ')';
					break;
				case 'unhtml':
					$e = 'htmlspecialchars(strip_tags(' . $e . '))';
					break;
				case 'camelcase':
					$e = 'ucfirst(' . $e . ')';
					break;
				case 'upper':
					$e = 'mb_strtoupper(' . $e . ')';
					break;
				default:
				}
			return '<?=' . $e . '?>';
		}
	}

	function udelta($time) {
		list($usec , $sec ) = explode(' ', $time);
		list($_usec, $_sec) = explode(' ', microtime());
		$s = (double)$_sec - (double)$sec;
		$m = ($s + (double)$_usec) - (double)($usec);

		return $m;
	}

	class View {
		static $keys = array();
		static $instance = null;
		const MSG_INFO  = 0;
		const MSG_WARN  = 1;
		const MSG_ERROR = 2;

		var $host;
		var $request;
		var $ctl;
		static $dlg = array();

		static $pctpl = array();
		static $templates = array();
		static $tplfiles = array();

		public function __construct() {
			$fe = FrontEnd::getInstance();
			$this->request= $fe->getRequest();
			$config = $fe->get('config');
			$l = $this->request->getList(true);
			$page = $l[0] ? strtolower($l[0]) : $config->get('main-controller');
			self::$keys[page] = $page;
			self::$keys[host] = 'http://' . $_SERVER['HTTP_HOST'];
			self::$keys[root] = 'http://' . make_domen($_SERVER['HTTP_HOST'], '');
			self::$instance = $this;
		}

		function getInstance() {
			return self::$instance;
		}

		public function renderButton($caption, $link, $inhref = true, $render = true) {
			$l = '<a class="btn" href="' . ($inhref ? $link : 'javascript:void(0);') . '"' . ($inhref ? '' : ' onclick="' . $link . '"') . '><span><span>' . $caption . '</span></span></a>';
			if ($render) echo $l;
			return $l;
		}

		public function renderMessage($message, $msgclass = self::MSG_INFO) {
			switch ($msgclass) {
				case self::MSG_INFO : $title = Loc::lget('info'); break;
				case self::MSG_WARN : $title = Loc::lget('warning'); break;
				case self::MSG_ERROR: $title = Loc::lget('error'); break;
			}
			echo '
			<script>$(document).ready(function(){show_error("' . $title . '", "' . urlencode($message) . '")});</script>
			';
		}

		function innerLink($link, $name, $style = null) {
			return '<a href="/' . $this->ctl->name() . '/' . $link . '"' . ($style ? ' style="' . $style . '"' : '') . '>' . $name . '</a>';
		}

		static function getPage() {
			return self::$keys['page'];
		}

		static function addKey($name, $value) {
			return self::$keys[$name] = $value;
		}

		static function process($contents2) {
			do {
				$contents1 = $contents2;
				$contents2 = preg_replace('/\\\n/', '<br />', $contents1);
				$contents2 = preg_replace_callback('/\{\%([^\:\%\#]+)(\:([^\#\%]+))?(\#([^\%]+))?\%\}/', _PTT, $contents2);
			} while ($contents1 != $contents2);
			return $contents2;
		}

		static function regTpl($template) {
			if (!isset(self::$templates[$template]))
				self::$templates[$template] = 1;
				self::$tplfiles[$template] = self::precompile($template);

			self::$pctpl[$template] = self::$tplfiles[$template];
		}

		static function getTpl($filename) {
			if ($template = array_search($filename, self::$tplfiles)) { // template already compiled
				if (self::$templates[$template] == 1) { // registered but not used yet
					self::$templates[$template]++;
					return file_get_contents($filename);
				} else
					return null; // registered and already used
			}
		}

		static public function precompile($template) {
			$file = self::findTPL($template, true);
			if (!$file) throw new Exception("Template [$template] don't exists!");
			$compiled = str_replace($template . '.tpl', 'cache/' . $template. '.tpl', $file);
			$m = @filemtime($compiled);
			$c = @filemtime($file);
//			echo "compile $file ($c) => $compiled ($m) ?<br />";
			if (!$m || ($m < $c)) {
				if (VIEW_COMPILE_MSGS)
					self::renderMessage('Template reassembled ['.$template.' => /cache/'.$template.']'.($m?' (diff: '.intval($c-$m).' sec)':''), View::MSG_INFO);

				$pctpl = self::$pctpl;
				self::$pctpl = array();
				$code = self::process(file_get_contents($file));

				$cpl = '';
				$t = "\nif (DEFINE_{%name}) {\ndefine('{DEFINE_{%name}}', 0);\n{%code}\n}\n\n";
				foreach (self::$pctpl as $fcpl) {
					$name = strtoupper(str_replace('-', '_', basename($fcpl, '.tpl')));
					$a = array('code' => self::getTpl($fcpl), 'name' => $name);
					$cpl .= patternize($t, $a);
				}

				$includes = $cpl ? '<?php' . PHP_EOL . $cpl . PHP_EOL . '?>' : '';
				self::$pctpl = array_merge($pctpl, self::$pctpl);
				$code = $includes . $code;
				assume_dir_exists(dirname($compiled));
				file_put_contents($compiled, $code);
				touch($compiled, $c);
			}

			return $compiled;
		}

		public function render($content) {
			self::addKey('content', $content->fetchAll());
			$start = getutime();
			$contents = $this->renderTPL('page', true);
			$end = getutime();
			$bytes = strlen($contents);
			$fe = FrontEnd::getInstance();
			$execution = tceil(udelta($fe->get('started')));
			$proc = tceil($end - $start);
			$conf = tceil(Config::$readtime);
			$tph = floor((1.0 / $execution) * 3600);
			$uph = floor($tph / 200);
			$upd = $uph * 24;
			$db = tceil(msqlDB::$ret);
			$q = msqlDB::$req;
			echo $contents . "\n<!-- Generated within $execution sec (TPL: $proc, CONF: $conf, DB: {$db}[$q queries]) -->\n<!-- Total ~$bytes bytes content -->\n<!-- Tease per hour: ~$tph (~$uph/$upd users per hour/day) -->";
			if (USE_TIMELEECH)
				TimeLeech::traceTimes();
		}

		public function renderTPL($view_template, $cache = false) {
			$l = self::precompile($view_template);
			if (!$l) {
				$error = '<!-- [' . $view_template . '] template must be here, but file not found -->' . PHP_EOL;
				if (!$cache) {echo $error; return;} else return $error;
			}

			if ($cache) ob_start();
//			echo PHP_EOL . '<!-- [' . $view_template . '] start -->' . PHP_EOL;
			require_once $l;
//			echo PHP_EOL . '<!-- [' . $view_template . '] end   -->' . PHP_EOL;
			if ($cache) {
				$r = ob_get_contents();
				ob_end_clean();
				return $r;
			}
		}

		static function findTPL($template, $nocache = false) {
			$fe = FrontEnd::getInstance();
			foreach ($fe->viewroot as $root) {
				$r  = $root . '/';
				$tpl= '/' . ($nocache ? '' : 'cache/') . $template . '.tpl';
				$l  = Loc::Locale();
//				echo '['.$r . $l . $tpl.']<br>';
				if (!file_exists($r . $l . $tpl)) {
					$la = Loc::$LOC_ALL;
					$l = '';
					foreach ($la as $locale) {
						if (file_exists($r . $locale . $tpl)) {
							$l = $locale;
							break;
						}}
					if ($l) return $r . $l . $tpl;
				} else
					return $r . $l . $tpl;
			}
			return null;// self::precompile($template);
		}

		static function getViewsDir($cache = true) {
			$d = array();
			$fe = FrontEnd::getInstance();
			foreach ($fe->viewroot as $root) {
				$r  = $root . '/' . Loc::Locale() . ($cache ? '/cache' : '');
				if (is_dir($r))
					$d[] = str_replace(ROOT, '', $r);
			}
			return $d;
		}

		static function clearCache() {
			foreach (self::getViewsDir() as $path) {
				cleanup_dir($path);
				@mkdir(ROOT . '/' . $r);
				return $path;
			}
			return false;
		}
	}
?>