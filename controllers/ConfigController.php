<?php

	function capitalize($str) {
		return strtoupper($str[0]) . substr($str, 1);
	}

	class ConfigController extends AdminViewController {
		protected $_name = 'config';

		public function actionMain($r) {
			if ($_POST[action] == save) {
				$data = $_POST;

				if (!isset($data[db][debug]))
					$data[db][debug] = 0;

				$config = &FrontEnd::getInstance()->get('config');
				foreach ($data as $section => $params)
					if (($s = $config->get($section)))
						foreach ($params as $param => $value)
							$config->set(array($section, $param), stripslashes($value));

//				debug($config);
				$config->save();
				header('Location: /config');
			}

			$this->view->renderTPL('functions/config');
		}

		public function actionInit($r) {
			$this->view->renderTPL('functions/init');
		}


	}
?>