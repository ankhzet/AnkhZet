<?php

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

				$offset = intval($config->get('main.time-offset'));
				$abbr = $tzh->getAbbreviations($offset);
				$zone = $zones[$config->get('main.time-zone')];
				$city = $abbr[$zone][0];
				$data['main']['timezone'] = "$zone/$city";

				foreach ($data as $section => $params)
					if (($s = $config->get($section)))
						foreach ($params as $param => $value)
							$config->set(array($section, $param), stripslashes($value));

//				debug($config);
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