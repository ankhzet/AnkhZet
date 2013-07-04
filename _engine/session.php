<?php

	require_once 'dbengine.php';
	require_once 'common.php';
	require_once 'dbtable.php';
	require_once 'localization.php';
	require_once 'user.php';

	class SAM {
		const SAM_NONE    = 0x000;
		const SAM_COOKIES = 0x001;
		const SAM_URI     = 0x002;
	}

	class Ses {
		var $uid;
		var $linked;
		var $locale = 0;
		private static $data = null;

		static function get($by = SAM::SAM_COOKIES) {
			if (!self::$data) self::$data = new Ses($by);
			return self::$data;
		}

		private function __construct($by) {
			switch ($by) {
			case SAM::SAM_NONE   : ;
				break;
			case SAM::SAM_COOKIES: $this->read(uri_frag($_COOKIE, 'ssid', null, 0), uri_frag($_COOKIE, 'user'), uri_frag($_COOKIE, 'locale'));
				break;
			case SAM::SAM_URI    : $this->read(uri_frag($_REQUEST,'ssid', null, 0), uri_frag($_REQUEST, 'user'), uri_frag($_REQUEST, 'locale'));
				break;
			}
			if (SAVE_SESSION_ACTIVITY && $this->valid()) {
				$db = msqlDB::o();
//				TimeLeech::addTimes('before update');
				$db->query('UPDATE LOW_PRIORITY `sessions` SET `activity` = ' . time() . ' WHERE `user` = ' . intval($this->linked) . ' limit 1');
//				TimeLeech::addTimes('after update');
//				unset($db);
			}
		}

		static function init_table() {
			msqlDB::o()->create_table('sessions', array(
				'`id` varchar(100) null'
			, '`user` int not null'
			, '`locale` int null'
			, '`activity` int null'
			, 'primary key (`id`)'
			), false);
		}

		function read($uid, $user, $locale) {
			if ($this->valid() && !isset($uid)) {
				$db = msqlDB::o();
				$db->delete('sessions', '`user` = \'' . $this->linked . '\'');
//				unset($db);
			}
			$this->uid    = null;
			$this->linked = null;
			$this->locale = isset($locale) ? $locale : 0;
			if (!isset($uid)) return;
			$o = msqlDB::o();
			$s = $o->select('sessions', '`id` = \'' . $uid . '\' limit 1', '`user`');
			$s = $s ? @mysql_fetch_row($s) : null;
			if ($s && intval($s[0]) == intval($user)) {
				$this->uid    = $uid;
				$this->linked = $user;
				if (isset($locale))
					$this->locale = $locale;
				else {
					$u = User::get();
					if ($u->ACL() > ACL::ACL_GUEST)
						$this->locale = $u->Lang();
				}
			}
//			unset($s);
		}

		function valid() {
			return isset($this->uid) && isset($this->linked);
		}

		function gen($user) {
			$this->uid    = md5(time() + '/' + rand() * 10000 + "salt" + $user);
			$this->linked = $user;
		}

		function write($to, $redirect = true) {
			if ($this->valid()) {
				$db = msqlDB::o();
				$db->delete('sessions', '`user` = \'' . $this->linked . '\'');
				$db->insert('sessions', array('id' => $this->uid, 'user' => $this->linked, 'locale' => $this->locale));
//				unset($db);
			}
			switch ($to) {
			case SAM::SAM_NONE   : break;
			case SAM::SAM_COOKIES:
				$t = time() + ($this->valid() ? 1 : -1) * 2592000;
				$t2= time() + 2592000;
				$host = $_SERVER['HTTP_HOST'];
				preg_match('/(.*\.|^)([^\.]+\.[^\.]+)$/i', $host, $m);
				$m = '.' . $m[2];
				setcookie('ssid', $this->uid, $t, "/", $m);
				setcookie('user', $this->linked, $t, "/", $m);
				setcookie('locale', $this->locale, $t2, "/", $m);
				if ($redirect) header('location: http://' . $host);
				break;
			case SAM::SAM_URI    : break;
			}
		}
	}
?>