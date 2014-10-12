<?php
	mb_internal_encoding('UTF-8');

	function sb_safeSubstr($str, $maxlen) {
//		$str = str_replace('<dd>', PHP_EOL, $str);
		if (($len = strlen($str)) > $maxlen) {
			$s = $str;
			$d = intval($maxlen * 0.1);
			do {
				if ($maxlen >= $len) {
					$str = $s;
					break;
				}
				$str = substr($s, 0, $maxlen);
				$maxlen += $d;


				preg_match('/^(.*[\.!\?]+)[^\.!\?]*$/s', $str, $matches);
				if (!!$matches) {
					$str = $matches[1];
					break;
				}
			} while ($maxlen <= $len);
			$str = close_tags($str);
		}

//		$str = str_replace(PHP_EOL, PHP_EOL . '<p>', close_tags($str));
		return $str;
	}

	function sb_safeSubstrl($str, $maxlen) {
//		$str = str_replace('<dd>', PHP_EOL, $str);
		if (($len = strlen($str)) > $maxlen) {
			$s = $str;
			$d = intval($maxlen * 0.1);
			do {
				if ($maxlen >= $len) {
					$str = $s;
					break;
				}
				$str = substr($s, -$maxlen);
				$maxlen += $d;


				preg_match('/^[^\.!\?]*[\.!\?]+(.*)$/s', $str, $matches);

				if (!!$matches) {
					$str = $matches[1];
					break;
				}
			} while ($maxlen <= $len);
			$str = close_tags($str);
		}

//		$str = str_replace(PHP_EOL, PHP_EOL . '<p>', close_tags($str));
		return $str;
	}


	function safeSubstr($str, $maxlen, $lines = 0) {
		if (($len = mb_strlen($str)) > $maxlen) {
			$str = str_replace(array(PHP_EOL, '<br />'), array('', PHP_EOL), $str);
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
			$str = str_replace(PHP_EOL, PHP_EOL . '<br />', close_tags($str));
		}

		return $str;
	}

	function safeSubstrl($str, $maxlen, $lines = 0) {
		if (($len = mb_strlen($str)) > $maxlen) {
			$str = str_replace(array(PHP_EOL, '<br />'), array('', PHP_EOL), $str);
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
			$str = str_replace(PHP_EOL, PHP_EOL . '<br />', close_tags($str));
		}

		return $str;
	}

	function close_tags($content, $co = false) {
		$content = preg_replace('#\<[^>]*$#', '', $content);
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
		$ignored_tags = array('br', 'hr', 'img', 'p', 'dd');

		$lastUnclosed = array();
		$unclosedPositionOffset = array();
		while (($position = @strpos($content, '<', $position)) !== FALSE) {
			//забираем все теги из контента

			if (preg_match('|^<(/?)([a-z\d]+)\b[^>]*>|i', substr($content, $position), $match, PREG_OFFSET_CAPTURE)) {
				$tag = strtolower($match[2][0]);
				//игнорируем все одиночные теги
				if ((in_array($tag, $ignored_tags) == FALSE) && isset($match[1][0])) {
					//тег открыт
					if ($match[1][0] == '') {
						if (isset($open_tags[$tag])) {
							if ($open_tags[$tag][0] == 0)
								$open_tags[$tag][1] = $position;
							$open_tags[$tag][0]++;
						} else {
							$open_tags[$tag][0] = 1;
							$open_tags[$tag][1] = $position;
						}
						array_unshift($lastUnclosed, $tag);
						array_unshift($unclosedPositionOffset, strlen($match[0][0]));
					}
					//тег закрыт
					if ($match[1][0] == '/') {
						if (isset($open_tags[$tag]) && ($open_tags[$tag][0] > 0)) {
							$open_tags[$tag][0]--;
							if (isset($lastUnclosed[0]) && ($lastUnclosed[0] == $tag)) {
								array_shift($lastUnclosed);
								array_shift($unclosedPositionOffset);
							}
						} else { // closed but not opened
							$offset = isset($lastUnclosed[0]) ? $unclosedPositionOffset[0] : 0;
							$unclosedPosition = $offset
								? $open_tags[$lastUnclosed[0]][1] + $offset
								: 0;
							$insertTag = "<$tag>";
							$content = substr_replace($content, $insertTag, $unclosedPosition, 0);
							$position += $offset;
						}
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

	function mb_ucfirst($str, $utf8 = true) {
		if ($utf8) $str = mb_convert_encoding($str, 'CP1251');
		$str = ucfirst(strtolower($str));
		return $utf8 ? mb_convert_encoding($str, 'UTF-8', 'CP1251') : $str;
	}

	function strtolower_ru($text) {
		$alphalo = array('ё','й','ц','у','к','е','н','г', 'ш','щ','з','х','ъ','ф','ы','в', 'а','п','р','о','л','д','ж','э', 'я','ч','с','м','и','т','ь','б','ю', 'і', 'є', 'ї');
		$alphahi = array('Ё','Й','Ц','У','К','Е','Н','Г', 'Ш','Щ','З','Х','Ъ','Ф','Ы','В', 'А','П','Р','О','Л','Д','Ж','Э', 'Я','Ч','С','М','И','Т','Ь','Б','Ю', 'І', 'Є', 'Ї');
		return str_replace($alphahi,$alphalo,$text);
	}

	function strtotrans($text) {
		$alphatra = array('e','j','c','u','k','e','n','g', 'sh','sch','z','h','','f','y','v', 'a','p','r','o','l','d','zh','e', 'ya','ch','s','m','i','t','','b','yu', 'i', 'ye', 'yi');
		$alphacyr = array('ё','й','ц','у','к','е','н','г', 'ш','щ','з','х','ъ','ф','ы','в', 'а','п','р','о','л','д','ж','э', 'я','ч','с','м','и','т','ь','б','ю', 'і', 'є', 'ї');
		return str_replace($alphacyr,$alphatra,$text);
	}

	function translit($cyrylic) {
		$r = '';
		foreach (preg_split('//u', $cyrylic, -1, PREG_SPLIT_NO_EMPTY) as $idx => $char) {
			$uchar = strtolower_ru($char);
			$r .= ($uchar == $char) ? strtotrans($char) : ucfirst(strtotrans($uchar));
		}

		return $r;
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

