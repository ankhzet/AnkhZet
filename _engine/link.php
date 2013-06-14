<?php
	require_once 'uri.php';

	class Tag_A {
		var $text = '';
		var $link = '';
		var $attr = '';
		var $uri  = null;

		public function Tag_A($data, $relative = true) {
			switch (true) {
				case is_string($data):
					$data = array('link'=>$data, 'text'=>$data);
					break;
				case is_array($data):
					break;
				default:
					throw new Exception('err_tagaparams', E_ERROR);
			}
			$this->init($data, $relative);
		}

		function init(array $data, $relative) {
			$text = $data['text'];
			$link = $data['link'];
			unset($data['text']);
			unset($data['link']);
			$attr = '';
			foreach ($data as $attrib => $value) $attr .= " {$attrib}='{$value}'";

			$this->text = $text;
			$this->link = $link;
			$this->attr = $attr;
			if ($relative) {
				require_once 'request.php';
				$u = new Request($_SERVER['REQUEST_URI']);
				$l = $u->getList();
				$a = $u->getArgs();
				unset($u);
			} else {
				$l = array();
				$a = array();
			}
			$p    = explode('/', $link);
			$path = implode('/', array_merge($l, $p));
			$this->uri = new URI(array('path'=>$path, 'args'=>$a));
		}

		public function __toString() {
			return '<a' . $this->attr . ' href=\'' . $this->uri . '\'>' . $this->text . '</a>';
		}

		public function render() {
			 echo $this->__toString();
		}
	}

?>