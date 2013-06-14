<?php

	class Request {
		public  $_uri    = null;
		public  $_params = null;
		private $_list   = null;
		private $_list1  = null;
		private $_args   = null;
		private $_actions= null;

		public function Request($URL) {
			$this->setURI($URL);
		}

		public function setURI($uri) {
			$this->_uri = urldecode($uri);// /*strtolower(*/$uri/*)*/;
			$this->normalize();
		}

		public function getURI() {
			return $this->_uri;
		}

		public function normalize() {
			$uri      = $this->_uri;
			$uri      = preg_replace('/[\\|\/]+/', '/', $uri);
			$uri      = preg_replace('/[\+]+/', ' ', $uri);
			$uri      = preg_replace('/[\'\"\`]+/', '', $uri);
			$uri      = preg_replace('/[\!\$\^\*\'\"\`\~]+/', '', $uri);
			$uri      = preg_replace('/(\/[\w\d_\(\)\[\]\{\}\;-]+\.php)/i', '/', $uri);
			$params   = '';
			if ($p = strpos($uri, '?')) {
				$params = substr($uri, $p + 1);
				$uri    = substr($uri, 0, $p);
			}

			$list     = array();
			$args     = array();

			$p        = explode('/', $uri);
			$a        = explode('&', $params);

			foreach ($p as $tag) if ($tag != '') $list[] = $tag;

			foreach ($a as $arg) if ($arg != '') {
				list($name, $param) = explode('=', $arg);
				$args[$name] = $param;
			}
			$args = array_merge($args, $_POST);

			$this->_uri    = $uri;
			$this->_params = $params;
			$this->_list   = $list;
			$this->_list1  = $list;
			$this->_args   = $args;
			$this->_actions= array();
		}

		public function getList($source = false) {
			if ($source)
				return $this->_list1;
			else
				return $this->_list;
		}

		public function getArgs() {
			return $this->_args;
		}

		public function setList($list = null, $source = false) {
			if (isset($list))
				$this->_list = is_array($list) ? $list : array($list);
			else
				$this->_list = array();

			if ($source)
				$this->_list1 = $this->_list;
		}

		public function getActions() {
			return $this->_actions;
		}

		public function setActions($actions = null) {
			if (isset($actions))
				$this->_actions = is_array($actions) ? $actions : array($actions);
			else
				$this->_actions = array();
		}

		public function pushAction($action) {
			array_push($this->_actions, (string) $action);
		}

		public function shiftAction() {
			$s = $this->_actions;
			$a = array_shift($s);
			$this->_actions = $s;
			return $a;
		}

 /*
	*
	input params:
		$post_file - variable in $_FILES array (e. g. $post_file = $_FILES['file_input_name'])
		$dir_to_save - destination dir
		$new_name - new name to save, or local file name else
	output:
		array(
			0 => upload status:
				 0 - uploaded succesful
				-1 - [move_uploaded_file()] failed (wrong dest dir, new filename or invalid tmp file)
				-2 - not a "uploaded file" (possibly hack attack)
				-3 - provided $post_file was not a valid $_FILES record
			1 => uploaded file name (or $new_name in case of -2 error code or $post_file in case of -3 error code)
		)
	*/
		function fileLoad($post_file, $dir_to_save, $new_name = null, $unique = true) {
			$res  = -3;
			if (is_array($post_file)) {
				$dir_to_save = SUB_DOMEN . $dir_to_save;
				$tmp  = $post_file['tmp_name'];
				if (is_uploaded_file($tmp)) {
					if (!$new_name) {
						$lcl  = $post_file['name'];
						$size = $post_file['size'];
						$dot  = strrpos($lcl, '.');
						$ext  = $dot ? substr($lcl, $dot) : '.png';
						$lcl  = $dot ? substr($lcl, 0, $dot) : $lcl;
						$new_name = $lcl . $ext;
						if ($unique && file_exists($dir_to_save . $new_name)) {
							$id = 0;
							do {
								$new_name = $lcl . '_' . (++$id) . $ext;
							} while(file_exists($dir_to_save . $new_name));
						}
					};
//					echo '[' . $dir_to_save . $new_name .']<br />';
					if (file_exists($dir_to_save . $new_name))
						@unlink($dir_to_save . $new_name);

//				debug($dir . $path);
					$res  = intval(move_uploaded_file($tmp, $dir_to_save . $new_name)) - 1;
				} else
					$res = -2;
			} else
				$new_name = $post_file;

			return array(0 => $res, 1 => $new_name);
		}

	}

?>