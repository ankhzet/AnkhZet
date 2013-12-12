<?php
	class XMLWorker {
		static $i = null;
		var $h = null;

		static function get() {
			if (!isset(self::$i))
				self::$i = new self();

			return self::$i;
		}

		protected function __construct() {
			$this->templates = $this->loadTemplates();
		}

		function loadTemplates() {
			return array(
				file_get_contents("cms://root/views/xml_response.tpl")
			, file_get_contents("cms://root/views/xml_response_item.tpl")
			);
		}

		function format($data) {
			$data['data'] = isset($data['data']) ? $this->formatArray("\t", 'data', $data['data']) : '';

			return patternize($this->templates[0], $data);
		}

		function formatArray($tab, $name, $a) {
			$keys = array_keys($a);
			$numeric = is_numeric($keys[0]);

			$p = array();
			foreach ($a as $key => $value) {
				if (preg_match('/[&<>"\']/', $value)) $value = "<![CDATA[{$value}]]>";
				$tag = $numeric ? 'item' : $key;
				$p[] = is_array($value) ? $this->formatArray("$tab\t", $tag, $value) : "$tab\t<$tag>{$value}</$tag>\n";
			}

			$p = join($p);
			return "$tab<$name>\n$p$tab</$name>\n";
		}
	}


	class RSSWorker extends XMLWorker {
		static function get() {
			if (!isset(self::$i))
				self::$i = new self();

			return self::$i;
		}

		function loadTemplates() {
			$path = 'cms://root/views';
			return array(
				file_get_contents("$path/rss_template.tpl")
			, file_get_contents("$path/rss_item_template.tpl")
			, file_get_contents("$path/rss_item_cdata.tpl")
			);
		}

		function format($data) {
			$i = array();
			if (isset($data['items']))
				foreach ($data['items'] as $item) {
					$item['description'] = patternize($this->templates[2], $item['description']);
					$i[] = patternize($this->templates[1], $item);
				}

			$data['items'] = join('', $i);
			return patternize($this->templates[0], $data);
		}

	}