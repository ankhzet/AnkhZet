<?php
	class API {
		static $i = null;

		static function get() {
			if (!isset(self::$i))
				self::$i = new self();

			return self::$i;
		}

		static function handle($request, $params) {
			$i = self::get();
			return method_exists($i, $method = 'action' . ucfirst($request))
				? $i->{$method}($params)
				: self::load($method, $params)
				;
		}

		private static function load($class, $params) {
			require_once 'loader.php';
			if (Loader::loadClass($class, array('controllers/api'))) {
				$instance = new $class();
				return $instance->execute($params);
			} else
				return false;
		}

		private function __construct() {
			require_once 'json.php';
		}

		static function getACLs() {
			return array(
				'rating' => 0
			, 'products' => 0
			, 'rss' => 0
			, 'grammar' => 0
			, 'nop'
			);
		}

		function actionNop($r) {
			JSON_Result(JSON_Ok);
		}
		function actionUserslist($r) {
			$d = msqlDB::o();
			$s = $d->select('users');
			$f = $d->fetchrows($s);
			JSON_Result(JSON_Ok, $f);
		}
		function actionDbquery($r) {
			$d = msqlDB::o();

			$q = explode('$$$', $r[query]);
			$e = array();
			foreach ($q as $subquery)
				if ($query = trim($subquery))
					$e[] = stripslashes($query);

			$r = array();
			foreach ($e as $query)
				$r[$query] = ($s = $d->query($query)) ? $d->fetchrows($s) : mysql_error($d->link);

			JSON_Result(JSON_Ok, $r);
		}

		function actionAcl($r) {
			$allow = !!$r['allow'];
			$user = $r['user'];
			$uri = $r['uri'];
			$file = 'cms://config/config.ini';
			$config = @file_get_contents($file);
			$config = preg_replace('/('.$user.'\.disallow\][^\[]*)(\d+\s*\=\s*'.$uri.'\/\*\s*)([^\[]*)\[/isU', '\\1\\3[', $config, 1);
			if (!$allow) {
				preg_match('/('.$user.'\.disallow\])[^\[]*(\d+)[^\d]+\[/isU', $config, $match);
				$max = intval($match[2]) + 1;
				$config = preg_replace('/('.$user.'\.disallow\][^\[]*)\[/isU', "\\01$max = $uri/*\r\n[", $config, 1);
			}
			JSON_Result(JSON_Ok, (@file_put_contents($file, $config) !== false) ? 'modified' : 'failed');
		}

		function actionDir($r) {
			$directory = preg_replace('/[^\w\d_\-\/]/', '', $r['directory']);
			if (count(array_intersect(explode('/', strtolower($directory)), array('_engine', 'controllers', 'models', 'views'))))
				JSON_Result(JSON_Fail, "Directory $directory not found");

			$filter = stripslashes($r['filter']);

			$dir = ROOT . '/' . $directory;
			$o = @dir($dir);
			$d = array();
			$f = array();
			if ($o)
				while (($entry = $o->read()) !== false) {
					if (($entry == '.') || ($entry == '..'))
						continue;

					if ($filter && !preg_match("/$filter/i", $entry))
						continue;

					if (is_dir($dir . '/' . $entry))
						$d[] = $entry;
					else
						$f[] = $entry;
				}
			else
				JSON_Result(JSON_Fail, "Directory $directory not found");

			$result = array();
			if (!!$r['dirs']) $result['dirs'] = $d;
			if (!!$r['files']) $result['files'] = $f;
			JSON_Result(JSON_Ok, $result);
		}

		function actionRating($p) {
			$class = 'product';
			$entity = intval($p['entity']);
			$vote = intval($p['vote']);
			require_once ROOT . '/models/core_ratings.php';
			$class = Ratings::get($class);
			$rating = $class->vote($entity, $vote);
			if ($rating !== false)
				JSON_Result(JSON_Ok, $rating);
			else
				JSON_Result(JSON_Fail, 'Already voted');
		}

		function actionClearcache($p) {
			require_once 'view.php';
			View::clearCache();
			$url = ($u = $_REQUEST['url']) ? preg_replace('/^\//', '', $u) : '/';
			if ($url == '') $url = '/';
			locate_to($url);
		}
		function actionClearthumbscache($p) {
			cleanup_thumbnails('');
			locate_to('/');
		}
		function actionToggleprofiler($p) {
			$f = file_get_contents('cms://root/index.php');
			preg_match('/define\(\'USE_TIMELEECH\',([^\)]+)\)/', $f, $m);
			$on = intval(trim($m[1]));
			$f = str_replace($m[0], 'define(\'USE_TIMELEECH\', ' . ($on ? '0' : '1') . ')', $f);
			file_put_contents('cms://root/index.php', $f);
			require_once 'view.php';
			View::clearCache();
			locate_to('/');
		}
	}

