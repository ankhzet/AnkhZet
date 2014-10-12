<?php
	require_once 'strings.php';

	define('THUMB_ROOT', '/data/thumbnails');
	if (!defined('USE_TIMELEECH'))
		define('USE_TIMELEECH', 0);

	function post($field) {
		return isset($_REQUEST[$field]) ? $_REQUEST[$field] : null;
	}

	function post_int($field) {
		return isset($_REQUEST[$field]) ? intval($_REQUEST[$field]) : 0;
	}

	function uri_frag(&$uri, $frag, $default = null, $cast_to_int = true) {
		$frag = isset($uri[$frag])
			? $uri[$frag]
			: $default;

		return $cast_to_int ? intval($frag) : $frag;
	}

	function fetch_field(&$array, $field, $index = null) {
		$result = array();
		if ($index)
			if (is_array($field))
				foreach ($array as &$row) {
					$r = array();
					foreach ($field as $f)
						$r[$f] = $row[$f];
					$result[$row[$index]] = $r;
				}
			else
				foreach ($array as &$row)
					$result[$row[$index]] = $row[$field];
		else
			if (is_array($field))
				foreach ($array as &$row) {
					$r = array();
					foreach ($field as $f)
						$r[$f] = $row[$f];
					$result[] = $r;
				}
			else
				foreach ($array as &$row)
					$result[] = $row[$field];

		return $result;
	}


	function sign($f) {
		return ($f == 0) ? 0 : $f / abs($f);
	}

	function aaxx($i, $word, $forms) {
		$p = $i % 100;
		$f = $i % 10;
		if ($p >= 11 && $p <= 19)
			return $word . $forms[2];
		else {
			if ($f == 1) return $word . $forms[0];
			if ($f >=2 && $f <= 4) return $word . $forms[1];
			if ($f == 0 || ($f >= 5 && $f <= 9)) return $word . $forms[2];
		}
	}


	function fs($i) {
		$m = array('байт', 'Кб', 'Мб', 'Гб');
		$s = 0;
		$u = 0;
		while ($i >= 1024) {
			$u = $i % 1024;
			$i = floor($i / 1024);
			$s++;
		}
		return ($i + (floor($u / 10.24) / 100)) . ' ' . $m[$s];
	}

	function assume_dir_exists($dir, $chmod = 0755) {
		$parent = dirname($dir);
		if (!is_dir($parent))
			assume_dir_exists($parent, $chmod);

		if (!is_dir($dir))
			mkdir($dir, $chmod);
	}

	function cleanup_dir($dir) {
		if (is_file($path = ((strpos($dir, 'cms:') === false) ? SUB_DOMEN . $dir : $dir)))
			return unlink($path);
		else {
			$d = dir($path);
			if ($d)
				while ($entry = $d->read())
					if (is_file($file = $path . '/' . $entry))
						unlink($file);
					else
						if (($entry != '.') && ($entry != '..'))
							cleanup_dir($dir . '/' . $entry);

			return rmdir($path);
		}
	}

	function cleanup_thumbnails($dir) {
		$d = @dir(SUB_DOMEN . THUMB_ROOT);
		$c = 0;
		if ($d)
			while ($entry = $d->read())
				if (intval($entry))
					$c += intval(cleanup_dir(THUMB_ROOT . '/' . $entry . $dir));

		return $c;
	}

	function make_domen($host, $domen) {
		return preg_match('/[^\.]+\.([^\.]+\..*)/', $host, $matches)
			? $matches[1] . '/' . $domen
			: $host . '/' . $domen;
	}

	function getmicrotime(){
		list($usec, $sec) = explode(' ', microtime());
		return ((double)$usec + (double)$sec);
	}

	function tceil($t) {
		return ceil($t * 10000) / 10000;;
	}

	class TimeLeech {
		static $times = array();
		static $timestart = 0;

		static function initTimes() {
			self::$timestart = getmicrotime();
		}

		static function addTimes($uid) {
			if (!USE_TIMELEECH) return;
			if (!self::$times[$uid])
				self::$times[$uid] = array();

			self::$times[$uid][] = getmicrotime();
		}

		static function traceTimes() {
			$last = getmicrotime();
			$start = self::$timestart;
			$u = $start;
			$b = 'start';
//			echo '<br /><hr />';
			$y = array();
			foreach (self::$times as $uid => $times) {
//				echo $uid . '=> <br />';
				foreach ($times as $time) {
					$total = $time - $start;
					$delta = $time - $u;
					$y[] = $total;
//					echo '&nbsp; &nbsp; &nbsp; &nbsp; ' . tceil($total) . ' ([' . $b . '] +' . tceil($delta) . ')' . '<br />';
				}
				$u = $times[0];
				$b = $uid;
//				echo '<br />';
			}

			$c = count($y);
			$start = self::$timestart;
			$u = $start;
			$t = array();
			foreach (self::$times as $uid => $times) {
				foreach ($times as $time) {
					$total = $time - $start;
					$delta = $time - $u;
					$t[] = array($total, $delta, $uid);
				}
				$u = $times[0];
			}

			$leech = array();
			foreach ($t as $idx => $d)
				$leech[] = array(
					"progress" => (100 * ($idx + 1) / $c)
				, "time" => $d[0]
				, "delta" => $d[1]
				, "uid" => addslashes($d[2])
				);

			file_put_contents(ROOT . '/timeleech.ini', serialize(
				array('leech' => $leech, 'data' => array('uri' => $_SERVER['REQUEST_URI']))
			));
			if (User::ACL() < ACL::ACL_ADMINS) return false;
			echo '<img class="profiler-chart" src="/models/core_timeleech.php?render=/timeleech.ini" />';
		}

	}

	TimeLeech::initTimes();

	function clousureList($begin, $end, $list, $joint, $pad) {
		$l = PHP_EOL . $begin . PHP_EOL . join(PHP_EOL . $joint, $list) . PHP_EOL . $end . PHP_EOL;
		return ($pad > 0) ? str_replace(PHP_EOL, PHP_EOL . str_repeat('	', $pad), $l) : $l;
	}

	function locate_to($location) {
		header('Location: ' . $location);
		exit();
	}

/* ---------- Exception handling -------------------------
 */

	function wrapExceptionTrace($e) {
		$message = $e->getMessage();
		$acl = User::get()->ACL() >= ACL::ACL_ADMINS;
		if ($acl) {
			$stack = $e->getTrace();
			$s = array();
			$c = count($stack);
			foreach ($stack as $trace) {
				$file = str_replace(array(ROOT, '\\'), array('.', '/'), $trace['file']);
				$line = $trace['line'];
				$call = $trace['class']
					? "({$trace['class']})?{$trace['type']}{$trace['function']}"
					: "{$trace['function']}";


				$a = array();
				if ($trace['args'])
					foreach ($trace['args'] as &$arg) {
						switch (gettype($arg)) {
						case 'integer':
						case 'int':
						case 'double': $a[] = $arg; break;
						case 'string':
							$arg = str_replace('<br />', PHP_EOL, safeSubstr($arg, 200, 10));
							$arg = preg_replace('/[' . PHP_EOL . '\s]{2,}/', PHP_EOL, $arg);
							$a[] = '"' . nl2br(htmlspecialchars($arg)) . '"';
							break;
						case 'boolean': $a[] = $arg ? 'true' : 'false'; break;
						case 'array': $a[] = 'Array()'; break;
						case 'object': $a[] = '' . get_class($arg) . '()'; break;
						case 'resource': $a[] = '(resource)'; break;
						default: $a[] = $arg;
						}
					}

				$call .= '(' . join(', ', $a) . ')';
				$tab = str_repeat('  ', $c--);
				if (!$file) {$file = '&lt;anonumuous&gt;'; $line = '?';}
				$s[] = "{$tab}<span class=\"filename\">{$file} ({$line})</span> <span class=\"call\">{$call}</span>";
			}
			$s = array_reverse($s);
			return '<div class="msg-box"><div class="message">' . $message . '</div><pre>Stack trace:<br />' . join(PHP_EOL, $s) . '</pre></div>';
		}
		return '<div class="msg-box"><div class="message">' . $message . '</div></div>';
	}

	function error_handler($code, $msg, $file, $line) {
		$severity = array(
			E_USER_ERROR => 'ERROR'
		, E_USER_WARNING => 'WARNING'
		, E_USER_NOTICE => 'NOTICE'
		, E_WARNING => 'WARNING'
		, E_NOTICE => 'NOTICE'
		);
		$severity = isset($severity[$code]) ? $severity[$code] : 'ERROR';
		$time = date('H:i:s');
		$file = str_replace(array(SUB_DOMEN, '\\'), array('', '/'), $file);
		$uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '&gt;no uri&lt;';
		$line = "\n[{$time}] {$severity} at {$file}({$line}): {$uri}\n\t\t\t\t\t\t\t{$msg}\n";
		_error_log($line);
	}

	function _error_log($line) {
		$date = date('d-m-Y');
		$log_file = "cms://logs/error-log-{$date}.php";
		if (!(is_file($log_file) && filesize($log_file))) {
			$line = "<?php \n\theader(\"Content-Type: text/html\");?><pre>\n{$line}";
		}
		error_log($line, 3, $log_file);
	}

/* ---------- Pattern templates handling -----------------
 */

	function patt_key($value) { return '{%' . $value . '}'; }
	function patt_key_loc($matches) { return Loc::lget(strtolower($matches[1])); }

	function patternize($pattern, &$data) {
		$pattern = str_replace(array_map('patt_key', array_keys($data)), $data, $pattern);
		if (strpos($pattern, '{%l:') !== false)
			$pattern = preg_replace_callback('"\{\%l:([^}]+)}"', 'patt_key_loc', $pattern);

		return $pattern;
	}

	function html_escape(&$row, $fields) {
		foreach ($fields as $field)
			$row[$field] = nl2br(htmlspecialchars($row[$field], ENT_QUOTES));
	}

