<?php

	class Loader {
		static $inc_path = '';

		public static function loadClass($name, $dirs = null) {
			if (class_exists($name, false) || interface_exists($name, false))
				return true;

			$path = self::makeIncludeList($dirs);
			self::check($name . $path['search']);

			if (self::loadFile($name . '.php', $path)) {
				return class_exists($name, false) || interface_exists($name, false);
			} else
				return false;
		}

		static function check($path) {
			$path = preg_replace('/' . PATH_SEPARATOR . '/', '', $path);
			if (preg_match('/[^a-z0-9\\/\\\\_.:-]/i', $path))
				throw new Exception('Unsafe class loading parameters for "' . $path . '"!');
		}

		static function makeIncludeList($dirs = null) {
			if (isset($dirs))
				if (!is_array($dirs))
					$dirs = array((string) $dirs);
				else;
			else
				$dirs = array();

			$path = get_include_path();
			return array('search' => join(PATH_SEPARATOR, array_merge($dirs, array($path))), 'basic' => $path);
		}

		static function loadFile($file, $path) {
			set_include_path($path['search']);

			$loaded = self::isReadable($file, $path['search']);

			if ($loaded)
				try {
					include $file;
				} catch (Exception $e) {
					$loaded = false;
					throw $e;
				}

			set_include_path($path['basic']);
			return $loaded;
		}

		static function isReadable($file, $path) {
			if (is_readable($file)) return true;

			$path = explode(PATH_SEPARATOR, $path);
			foreach ($path as $dir) {
				if ($dir != '.')
					if (is_readable($dir . '/' . $file))
						return true;
			}

			return false;
		}
	}