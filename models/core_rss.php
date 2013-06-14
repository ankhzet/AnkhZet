<?php
	class RSSWorker {
		static $i = null;
		var $h = null;

		static function get() {
			if (!isset(self::$i))
				self::$i = new self();

			return self::$i;
		}

		private function __construct() {
		}

		function loadTemplates() {
			$path = dirname(__FILE__);
			return array(
				@file_get_contents($path . '/rss_template.tpl')
			, @file_get_contents($path . '/rss_item_template.tpl')
			, @file_get_contents($path . '/rss_item_cdata.tpl')
			);
		}

		function output($data, $die = false) {
			$tpl = $this->loadTemplates();
			$i = array();
			if ($data['items'])
				foreach ($data['items'] as $item) {
					$item['description'] = patternize($tpl[2], $item['description']);
					$i[] = patternize($tpl[1], $item);
				}

			$data['items'] = join('', $i);
			echo patternize($tpl[0], $data);
			if ($die) die();
		}

	}
?>