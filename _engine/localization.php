<?php
	require_once 'config.php';
	require_once 'session.php';

	define('SPY_LOCALE', 0);

	class Loc extends Config {
		const LOC_RU  = 'ru';
		const LOC_EN  = 'en';
		const LOC_UA  = 'ua';
		static
			$LOC_ALL   = array(
				0 =>
					self::LOC_RU
			);
		static $locale = null, $locid = null, $config = null;

		static function lget($entry) {
			if (!isset(self::$config)) self::Locale();
			$l = self::$config->get(self::$locale . '.' . $entry);
			if (!$l) $l = self::$config->get(self::LOC_RU . '.' . $entry);

			if (SPY_LOCALE) {
				$log = @file_get_contents(ROOT . '/locale.log');
				$log = $log ? unserialize($log) : array();
				$log[$entry] = array('localization' => $l ? $l : $entry, 'hits' => intval($log[$entry]['hits']) + 1);
				@file_put_contents(ROOT . '/locale.log', serialize($log));
			}
			return $l ? $l : $entry;
		}

		static function Locale() {
			if (!isset(self::$config)) {
				$s = Ses::get();
				$locid = $s->locale ? $s->locale : User::Lang();
				$locid = (!isset(self::$LOC_ALL[$locid])) ? 0 : $locid;
				self::$locid = $locid >= count(self::$LOC_ALL) ? 0 : $locid;
				self::$locale= self::$LOC_ALL[$locid];
				self::$config = self::read('INI', 'cms://root/locale.ini');
			}
			return self::$locale;
		}

		static function locName($loc) {
			if (!isset(self::$config)) self::Locale();
			$l = self::$config->get(array('main', 'loc_' . self::$LOC_ALL[$loc]));
			return $l ? $l : $loc;
		}
	}
