<?php
	class Response {
		private $_headers = null;
		private $_contents= null;

		public function Response() {
			$this->_headers = array();
			$this->_contents= array('body' => '');
		}

		public function setHeaders($type, $content, $append = false) {
			if ($append && isset($this->_headers[$type]))
				$this->_headers[$type] = array(0 => $this->_headers[$type], 1 => (string) $content);
			else
				$this->_headers[$type] = (string) $content;
		}

		public function getHeader($type, $content) {
			return $this->_headers[$type];
		}

		public function append($content, $section = 'body') {
			$this->_contents[$section] .= $content;
		}

		public function prepend($content, $section = 'body') {
			$this->_contents[$section] = $content . $this->_contents[$section];
		}

		public function set($content, $section = 'body') {
			$this->_contents[$section] .= $content;
		}

		public function sendAll() {
			if (!headers_sent())
				header("cache-control: no-cache");
				foreach ($this->_headers as $type => $header) {
					while (is_array($header)) {
						header($type . ": " . $header[1]);
						$header = $header[0];
					};
					header($type . ": " . $header);
				}

			foreach ($this->_contents as $section)
				echo $section;
		}

		public function fetchAll() {
			return join(PHP_EOL, $this->_contents);
		}
	}
?>
