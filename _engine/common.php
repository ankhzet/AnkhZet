<?php
	define('THUMB_ROOT', '/data/thumbnails');

	mb_internal_encoding('UTF-8');

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

	function mb_ucfirst($str, $utf8 = true) {
		if ($utf8) $str = mb_convert_encoding($str, 'CP1251');
		$str = ucfirst(strtolower($str));
		return $utf8 ? mb_convert_encoding($str, 'UTF-8', 'CP1251') : $str;
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
			@mkdir($dir, $chmod);
	}

	function cleanup_dir($dir) {
		if (is_file($path = SUB_DOMEN . $dir))
			return @unlink($path);
		else {
			$d = @dir($path);
			if ($d)
				while ($entry = $d->read())
					if (is_file($file = $path . '/' . $entry))
						@unlink($file);
					else
						if (($entry != '.') && ($entry != '..'))
							cleanup_dir($dir . '/' . $entry);

			return @rmdir($path);
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

	function close_tags($content, $co = false) {
		if ($co) {
//      echo htmlspecialchars($content) . ' <br> <p>';

			$ot = 0;
			for ($i = strlen($content); $i > 0; $i--) {
				if ($content[$i] == '>') $ot++;
				if ($content[$i] == '<') {
					if ($ot <= 0) $content .= '>';
					break;
				}
			}
//      echo htmlspecialchars($content);
		}
		$position = 0;
		$open_tags = array();
		//теги для игнорирования
		$ignored_tags = array('br', 'hr', 'img', 'p');

		while (($position = @strpos($content, '<', $position)) !== FALSE) {
			//забираем все теги из контента

			if (preg_match('|^<(/?)([a-z\d]+)\b[^>]*>|i', substr($content, $position), $match, PREG_OFFSET_CAPTURE)) {
				$tag = strtolower($match[2][0]);
				//игнорируем все одиночные теги
				if (in_array($tag, $ignored_tags) == FALSE) {
					//тег открыт
					if (isset($match[1][0]) AND ($match[1][0] == '')) {
						if (isset($open_tags[$tag])) {
							if ($open_tags[$tag][0] == 0)
								$open_tags[$tag][1] = $position;
							$open_tags[$tag][0]++;
						} else {
							$open_tags[$tag][0] = 1;
							$open_tags[$tag][1] = $position;
						}
					}
					//тег закрыт
					if (isset($match[1][0]) AND ($match[1][0] == '/')) {
						if (isset($open_tags[$tag]))
							$open_tags[$tag][0]--;
					}
				}
				$position += strlen($match[0][0]);
			}
			else
				$position++;
		}

		//закрываем все теги
		$a = array();
		$n = 0;
		$t = '';
		foreach ($open_tags as $tag => $c)
			if ($c[0] > 0)
				$t = str_repeat('</' . $tag . '>', $c[0]) . $t;

		return $content . $t;
	}


	function fixLongStr($str, $len, $lines = 0, $repl = '...') {
		$str = close_tags(str_replace(array('<p>', '</p>'), array('<br>', ''), trim($str)));
		if (strlen($str) > $len) {
			$i = $len - 3;
			do {
				$i--;
			} while ($i && (!preg_match('/^[\n\ \.,\!\@\#\$\%\^\&\*\=\+]?$/i', $str[$i])));
			$str = close_tags(substr($str, 0, $i), true) . $repl;
		}
		if ($lines) {
			$c   = preg_match_all('/(\n)/', $str, $m, PREG_OFFSET_CAPTURE);
			if ($c >= $lines) $str = close_tags(substr($str, 0, $m[0][$lines - 1][1]), true) . $repl;
		}
		return $str;
	}

	function safeSubstr($str, $maxlen, $lines = 0) {
		if (($len = mb_strlen($str)) > $maxlen) {
			$str = str_replace('<br />', PHP_EOL, $str);
			$s = $str;
			$d = intval($maxlen * 0.1);
			do {
				if ($maxlen >= $len) {
					$str = $s;
					break;
				}
				$str = rtrim(mb_substr($s, 0, $maxlen - 3));
				$maxlen += $d;

				if ($lines) {
					$l = explode(PHP_EOL, $str);
					if ($lines < count($l))
						$str = join(PHP_EOL, array_slice($l, 0, $lines));
				}
				preg_match('/(.*[\.!\?]+)[^\.!\?]*$/is', $str, $matches);
				if (!!$matches) {
					preg_match('/^(.+)([\.!\?]+)\P{L}*$/isu', rtrim(@$matches[1]), $matches);
					$str = rtrim(@$matches[1]) . @$matches[2] . '..';
				} else
					break;
			} while (mb_strlen($matches[1]) <= 0);
			$str = str_replace(PHP_EOL, '<br />', close_tags($str));
		}

		return $str;
	}

	function safeSubstrl($str, $maxlen, $lines = 0) {
		if (($len = mb_strlen($str)) > $maxlen) {
			$str = str_replace('<br />', PHP_EOL, $str);
			$s = $str;
			$d = intval($maxlen * 0.1);
			do {
				if ($maxlen >= $len) {
					$str = $s;
					break;
				}
				$str = rtrim(mb_substr($s, - ($maxlen - 3)));
				$maxlen += $d;

				if ($lines) {
					$l = explode(PHP_EOL, $str);
					if ($lines < count($l))
						$str = join(PHP_EOL, array_slice($l, 0, $lines));
				}
				preg_match('/^[^\.!\?]*([\.!\?]+.*)/is', $str, $matches);
				if (!!$matches) {
					preg_match('/\P{L}*([\.!\?]+)(.+)$/isu', rtrim($matches[1]), $matches);
					$str = $matches[1] . '..' . rtrim($matches[2]);
				} else
					break;
			} while (mb_strlen($matches[1]) <= 0);
			$str = str_replace(PHP_EOL, '<br />', close_tags($str));
		}

		return $str;
	}

	function safeJoin($glue, $pieces) {
		$r = '';
		foreach ($pieces as $piece) {
			if ($piece)
				if ($r)
					$r .= $glue . $piece;
				else
					$r = $piece;
		}
		return $r;
	}

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
		$severity = @$severity[$code] ? $severity[$code] : 'ERROR';
		$date = date('d-m-Y');
		$time = date('H:i:s');
		$file = str_replace(array(SUB_DOMEN, '\\'), array('', '/'), $file);
		$line = "\n[{$time}] {$severity} at {$file}:{$line}:\n\t\t\t\t\t\t\t{$msg}\n";
		$log_file = "cms://logs/error-log-{$date}.php";

		if (!is_file($log_file) || !filesize($log_file)) $line = "<?php ?><pre>\n{$line}";
		if ($f = fopen($log_file, 'a')) {
			fwrite($f, $line);
			fclose($f);
		}
	}

/* ---------- Pattern templates handling -----------------
 */

	function patt_key($value) { return '{%' . $value . '}'; }

	function patternize($pattern, &$data) {
		return str_replace(array_map('patt_key', array_keys($data)), $data, $pattern);
	}

	function html_escape(&$row, $fields) {
		foreach ($fields as $field)
			$row[$field] = nl2br(htmlspecialchars($row[$field], ENT_QUOTES));
	}

