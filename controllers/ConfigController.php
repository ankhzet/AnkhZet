<?php
	require_once ENGINE_ROOT . '/datetime.php';

	class ConfigController extends AdminViewController {
		protected $_name = 'config';

		public function actionMain($r) {
			$config = FrontEnd::getInstance()->get('config');
			$tzh = TimeZoneHelper::get();
			$zones = $tzh->getZones();
			if (post('action') == 'save') {
				$data = $_POST;

				if (!isset($data['db']['debug']))
					$data['db']['debug'] = 0;
				if (!isset($data['main']['offline']))
					$data['main']['offline'] = 0;

				$offset = $config->get('main.time-offset');
				$timezone = $config->get('main.time-zone');
				$zone = $zones[$timezone];
				$cities = $tzh->getOffsets($zone);
				$o = $tzh->getAbbreviations(intval($offset));
				$o = $o[$zone];
				$list = explode(', ', $cities[$offset]);
				$c = array();
				foreach ($cities as $i => $city_list)
					$c[$k = intval($i)] = array_merge(explode(', ', $city_list), isset($c[$k = intval($i)]) ? $c[$k = intval($i)] : array());

				foreach ($c as $i => $clist) {
					$c[$i] = array_unique($clist);
					sort($c[$i]);
				}
				ksort($c);

				$k = array_keys($c);
				$lo = intval(array_shift($k));
				$hi = intval(array_pop($k));
				if ($hi === false) $hi = $lo;

				$p = intval($offset);
				$i1 = $p - 1;
				$i2 = $p + 1;
				if ($i1 < $lo) $i1 = $lo;
				if ($i2 > $hi) $i2 = $hi;

				$p1 = array_diff($list, $c[$i1]);
				$p2 = array_diff($list, $c[$i2]);
				$p = array_intersect($p1, $p2);
				$found = array_shift($p);

//				debug2(array($p, "$zone/$found", $offset, $timezone, $zone));
				if ($found)
					$data['main']['timezone'] = "$zone/$found";
				else
					$this->view->renderMessage('Cities from selected timezone overlaps with cities in rear timezones, please, select another cities in same timezone', MSG_ERROR);

				if ($found) {
					foreach ($data as $section => $params)
						if (($s = $config->get($section)))
							foreach ($params as $param => $value)
								$config->set(array($section, $param), stripslashes($value));

//				debug($config);
				}

				$config->save();
				locate_to('/config');
			}

			$this->view->zones = $zones;
			$this->view->offsets = $offsets = $tzh->getOffsets($c = $config->get('time.zone') ? $c : 'Europe');

			$this->view->renderTPL('functions/config');
		}

		public function actionInit($r) {
			$this->view->renderTPL('functions/init');
		}


	}
?>