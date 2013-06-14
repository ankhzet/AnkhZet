<?php
	class URI {
		private $schema = null;
		private $domain = null;
		private $host   = null;
		private $anchor = null;
		private $path   = null;
		private $args   = null;

		public function URI($data) {
			$this->schema = isset($data['schema']) ? $data['schema'] : 'http://';
			$this->domain = isset($data['domain']) ? $data['domain'] : '';
			$this->host   = isset($data['host'])   ? $data['host']   : $_SERVER['HTTP_HOST'];
			$this->anchor = isset($data['anchor']) ? $data['anchor'] : '';
			$this->path   = isset($data['path'])   ? $data['path']   : 'index';
			$this->args   = isset($data['args'])   ? $data['args']   : array();
		}

		public function __toString() {
			$schema = $this->schema;
			$domen  = $this->domen;
			$host   = $this->host;
			$path   = $this->path;
			$anchor = $this->anchor;
			$args   = $this->args;

			if (preg_match('/^http[s]?:\/\/$/', $schema) == 0) $schema = 'http://';
//      if (preg_match('/^www\.$/', $host) == 0) $host = 'www.' . $host;
			if (preg_match('/^(.+\.|)$/', $domain) == 0) $domain .= '.';
			if (preg_match('/^(\/.+|)$/', $path) == 0) $path = '/' . $path;
			$a = '';
			foreach ($args as $arg => $value)
				if (isset($arg) && is_string($arg) && ($arg != ''))
					$a .= (($a != '') ? '&' : '?') . $arg . ($value != '' ? '=' . urlencode($value) : '');

			return $schema . $domain . $host . $path . ($anchor != '' ? '#' . $anchor : '') . $a;
		}
	}
?>