<?
	define(ROOT, dirname(dirname(__FILE__)));
	define(ENGINE_ROOT, ROOT . '/_engine');
	define(CTL_ROOT, ROOT . '/controllers');
	define(VIEWS_ROOT, ROOT . '/views');
	define(MODELS_ROOT, ROOT . '/models');
	define(SUB_CTLS, SUB_DOMEN . '/controllers');
	define(SUB_VIEWS, SUB_DOMEN . '/views');
	define(SUB_MODELS, SUB_DOMEN . '/models');

	require_once 'dbengine.php';
	require_once 'session.php';

	function width($txt, $size, $font) {
		$b = ImageTTFBBox($size, 0, $font, $txt);
		return $b[2];
	}

	class Captcha {
		var $uid  = 0;
		var $hash = '';
		var $time = 0;

		public function __construct($uid = null) {
			$this->uid = $uid;
			if ($this->uid == '') return;

			$dbc = msqlDB::o();
			$s   = $dbc->select('_captcha', '`uid` = \'' . $this->uid . '\'', '`hash`, `time`');
			$r   = $s ? @mysql_fetch_row($s) : array('', 0);
			$this->hash = $r[0];
			$this->time = intval($r[1]);
		}

		function valid($hash) {
			$valid = $hash && $hash == $this->hash;
			if ($valid) {
				$dbc = msqlDB::o();
				$s   = $dbc->delete('_captcha', array('uid' => $this->uid));
			}
			return $valid;
		}

		function genCaptchaStr($len) {
			$result = '';
			$offseta = ord('a');
			$offset0 = ord('0');
			while ($len--) {
				do {
					$char = rand(0, 35);
					$char += $char < 26 ? $offseta : $offset0 - 26;
				} while (preg_match('/^[oiljtf01]$/i', chr($char)));
				$result .= chr($char);
			}
			return $result;
		}

		function generate() {
			$s = Ses::get()->uid;
			if (!$s) $s = dechex(join(explode('.', $_SERVER['REMOTE_ADDR'])));
			$this->uid  = $s . '*' . dechex(rand(1, 0xFFFF));
			$this->hash = $this->genCaptchaStr(6 + rand(0, 2));
			$dbc = msqlDB::o();
			$s   = $dbc->delete('_captcha', '(`uid` like \'' . $s . '*%\') or (`time` < ' . (time() - 3600) . ')');
			$s   = $dbc->insert('_captcha', array('uid' => $this->uid, 'hash' => $this->hash, 'time' => time()));
			return $this->uid;
		}

		function image() {
			$w     = 109;
			$h     = 32;
			$font  = dirname(__FILE__) . '/comic.ttf';
			$fontSize = 14;

			$img   = ImageCreateTrueColor($w, $h);
			$black = ImageColorAllocate($img, 0, 0, 0);
			$back  = ImageColorAllocate($img, 255, 255, 205);

			$r     = array(
				ImageColorAllocate($img, 255, 140, 0),
				ImageColorAllocate($img, 186, 180, 133),
				ImageColorAllocate($img, 47, 79, 79),
				ImageColorAllocate($img, 205, 179, 139),
			);

			$color = $r[rand(0, 3)];
			ImageFill($img, 0, 0, $back);
			ImageRectangle($img, 0, 0, $w - 1, $h - 1, $black);

			$hash = $this->hash;
			$c = strlen($hash);
			$d = width($hash, $fontSize, $font);
			ImageTTFText($img, $fontSize, 0, 10 + rand(0, $w - $d - 20), $fontSize + rand(5, $h - $fontSize - 8), $color, $font, $hash);


			$ext = 'png';
			header('content-type: image/' . $ext);
			header('cache-control: no-cache');
			header('cache-control: max-age=3600');
//			sleep(1);
			imagepng($img);
		}
	}
?>