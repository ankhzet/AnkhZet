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
			$file = ENGINE_ROOT . '/config.ini';
			$config = @file_get_contents($file);
			$config = preg_replace('/('.$user.'\.disallow\][^\[]*)(\d+\s*\=\s*'.$uri.'\/\*\s*)([^\[]*)\[/isU', '\\1\\3[', $config, 1);
			if (!$allow) {
				preg_match('/('.$user.'\.disallow\])[^\[]*(\d+)[^\d]+\[/isU', $config, &$match);
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

		function actionBanners($r) {
			$banners = ($b = @unserialize(@file_get_contents(ROOT . '/banners.ini'))) ? $b : array(
				'rotator' => array()
			, 'top' => array('url' => '', 'img' => '')
			, 'bottom' => array('url' => '', 'img' => '')
			);
			$remove = !!$r['remove'];
			$url = $r['url'];
			$img = $r['img'];
			switch ($sub = $r['sub']) {
			case "rotator":
				if (!$remove) {
					foreach ($banners['rotator'] as $idx => $banner)
						if (($img == $banner['img']) || ($url && ($url == $banner['url'])))
							JSON_Result(JSON_Fail, 'Already in rotator');

					$banners['rotator'][] = array('img' => $img, 'url' => $url);
				} else {
					$i = -1;
					foreach ($banners['rotator'] as $idx => $banner)
						if ($img == $banner['img']) {
							$i = $idx;
							break;
						}
					unset($banners['rotator'][$i]);
				}
				break;
			case "top":
			case "bottom":
				if (!$remove) {
					if (($img == $banners[$sub]['img']) && ($url == $banners[$sub]['url']))
						JSON_Result(JSON_Fail, 'Already the same');
					$banners[$sub] = array('img' => $img, 'url' => $url);
				} else
					$banners[$sub] = array('img' => '/theme/img/banner.png', 'url' => '');
				break;
			default:
				JSON_Result(JSON_Fail, 'Target unknown');
			}

			@file_put_contents(ROOT . '/banners.ini', serialize($banners));
			require_once 'view.php';
			$pattern = View::findTPL('banners', true);
			$static = View::findTPL('static/banners', true);
			$code = file_get_contents($pattern);
			$b = array();
			$l = array();
			$i = 1;
			foreach ($banners['rotator'] as $banner) {
				$l[] = '<li' . (($i == 1) ? ' class="active"' : '') . '>' . ($i++) . '</li>';
				$banner['url'] = ($url = $banner['url']) ? $url : 'javascript:void(0)';
				$b[] = patternize('<a href="{%url}"><img src="/data/share/{%img}" /></a>', $banner);
			}
			$banners['buttons'] = join('', $l);
			$banners['rotator'] = join(PHP_EOL, $b);
			if ($banners['top']['img'] != '/theme/img/banner.png') $banners['top']['img'] = '/data/share/' . $banners['top']['img'];
			if ($banners['bottom']['img'] != '/theme/img/banner.png') $banners['bottom']['img'] = '/data/share/' . $banners['bottom']['img'];
			$banners['top']['url'] = ($url = $banners['top']['url']) ? $url : 'javascript:void(0)';
			$banners['bottom']['url'] = ($url = $banners['bottom']['url']) ? $url : 'javascript:void(0)';
			$banners['top'] = patternize('<a href="{%url}"><img src="{%img}" /></a>', $banners['top']);
			$banners['bottom'] = patternize('<a href="{%url}"><img src="{%img}" /></a>', $banners['bottom']);

			$code = patternize($code, $banners);
			View::clearCache();
			file_put_contents($static, $code);

			JSON_Result(JSON_Ok, urlencode($code));
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

		function actionProducts($p) {
			$c = ($c = intval($p['category'])) ? $c : 0;
			if ($c) {
				require_once ROOT . '/models/core_categories.php';
				$cns = CategoriesNS::get();
				$ch = $cns->fetch_childs($c, 'id', 'level > 1');
				$ids = array();
				foreach ($ch as $row)
					$ids[] = intval($row['id']);

				$ids = '`category` in (' . join(',', $ids) . ')';
			} else
				$ids = '`id` = ' . ($pid = intval($p['id']));

			require_once ROOT . '/models/core_products.php';
			$a = ProductsAggregator::getInstance();
			$f = $a->fetch(array('nocalc' => true, 'desc' => 0, 'collumns' => '`id`, `title`, `category`, `brand`, `price`, `priceoff`', 'filter' => $ids, 'pagesize' => 100, 'page' => 0));
			$f = $a->fetchData($f,
			$pid
			? '
<img src="/data/previews/{%id}.jpg?resolution=80" alt="{%catname} {%brandname} {%title}" />
<div class="info">
<div class="name"><a href="/product/id/{%id}">{%catname} {%brandname} {%title}</a><br /><span>{%price} руб.</span></div>
<div class="add_link"><a href="/order/shopcart?product={%id}">Добавить в корзину</a></div>
</div>'
			: '
<div class="product">
<img src="/data/previews/{%id}.jpg?resolution=80" />
<div class="info">
<b>{%catname}</b><br/><b>{%brandname}</b><br/><b>{%title}</b><br/>
<span class="id-link">{%price} руб.<span class="pull_right">ID: <a href="/product/id/{%id}" target="_blank">{%id}</a></span></span>
</div>
</div>'
			);
			foreach ($f as &$item)
				$item = urlencode($item);

			JSON_Result(JSON_Ok, $f);
		}

		function actionMenu($p) {
			$platform = $p['platform'];
			require_once 'static.php';
			$s = StaticTemplate::get('menu', array('main' => 'menu', 'static' => 'static/menu', 'banner' => 'menu_banner'));

			switch ($p['target']) {
			case 'product':
				$s->config['product' . $platform] = intval($p['product']);
				$s->bakeStatic();
				$s->save();
				JSON_Result(JSON_Ok, urlencode($s->getResult()));
				break;
			case 'banner':
				if ($p['img'])
					$s->config['banner' . $platform] = '<a href="'.$p['url'].'"><img src="/data/share/'.$p['img'].'" /></a>';
				else
					unset($s->config['banner' . $platform]);

//				debug2($s->config);
				$s->bakeStatic();
				$s->save();
				JSON_Result(JSON_Ok, urlencode($s->getResult()));
				break;
			}


			JSON_Result(JSON_Fail, 'UNKNOWN TARGET');
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
			$f = file_get_contents(ROOT . '/index.php');
			preg_match('/define\(\'USE_TIMELEECH\',([^\)]+)\)/', $f, $m);
			$on = intval(trim($m[1]));
			$f = str_replace($m[0], 'define(\'USE_TIMELEECH\', ' . ($on ? '0' : '1') . ')', $f);
			file_put_contents(ROOT . '/index.php', $f);
			require_once 'view.php';
			View::clearCache();
			locate_to('/');
		}
	}

?>