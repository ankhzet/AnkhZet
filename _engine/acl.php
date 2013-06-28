<?php
	class ACL {
		const ACL_GUEST  = 0x00000000;
		const ACL_USER   = 0x00000001;
		const ACL_MODER  = 0x00000002;
		const ACL_BANNED = 0x00000003;
		const ACL_ADMINS = 0x00001000;

		static $data = array();

		const   ACC_ALL  = '*';
		const   ACC_ADMIN= 'admin';

		private $UID     = 0;
		private $name    = 'guest';
		private $parent  = null;
//		private $allow   = array();
		private $disallow= array();

		//prevent instantiating
		private function __construct($UID, $name, $parent) {
			$this->UID    = $UID;
			$this->name   = $name;
			$this->parent = $parent;
		}

		function get($prop) {
			return $this->{$prop};
		}

/*		private function allowThis($route) {
			if (is_array($route))
				$this->allow = $route;
			else
				$this->allow   = array();
		}*/
		private function disallowThis($route) {
			if (is_array($route))
				$this->disallow = $route;
			else
				$this->disallow   = array();
		}

		private function allowedThis($routeList) {
			$disallowed = false;//count($this->allow) == 0;

/*			if (!$disallowed) {
				$disallowed = true;
				foreach ($this->allow as $route) {
					$list = $routeList;
					foreach ($route as $node) {
						if ($node == self::ACC_ALL) {
							$disallowed = false;
							break;
						}
						$lnode = array_shift($list);
						if ($node != $lnode) break;
					}
					if (!$disallowed) break;
				}
			}*/

//			if ($disallowed)
//				$disallowed = ($this->parent == null) || !$this->parent->allowedThis($routeList);
//			else
				foreach ($this->disallow as $route) {
					$list = $routeList;
					foreach ($route as $node) {
						if ($node == self::ACC_ALL) {
							$disallowed = true;
							break;
						}
						$lnode = array_shift($list);
						if ($node != $lnode) break;
					}
					if ($disallowed) break;
				}

			return !$disallowed;
		}

		static function addACL($UID, $name, $inherits = null) {
			if (!self::isUID($UID)) throw new Exception("Specified UID($UID) for ACL [$name] isn\'t unique");
			self::$data[$UID] = new ACL($UID, $name, $inherits);
		}

/*		static function allow($UID, $route) {
			if (isset(self::$data[$UID])) self::$data[$UID]->allowThis($route);
		}*/

		static function disallow($UID, $route) {
			if (isset(self::$data[$UID])) self::$data[$UID]->disallowThis($route);
		}

		static function allowed($UID, $route) {
			return isset(self::$data[$UID]) && self::$data[$UID]->allowedThis(is_array($route) ? $route : explode('/', $route));
		}

		static function isUID($UID) {
			return !isset(self::$data[$UID]);
		}

		static function readConfig($cfg) {
			$acl = $cfg->get('acl');
			if (!count($acl)) return false;

//       echo "<pre>";
//       print_r($acl);
			foreach ($acl as $name => $value) {
				$id = @$value['id'];
				$pr = @self::$data[$acl[$value['parent']]['id']];
				$al = @$value['allow'];
				$dl = @$value['disallow'];
//         echo "[ACL::{$pr}::$name={{$id}:[$al/$dl]}]<br>";
				self::addACL((int)$id, $name, $pr);
//				self::allow($id, make_route($al));
				self::disallow($id, make_route($dl));
			}
//       print_r(self::$data);
//       echo "</pre>";
		}
	}

	function make_route($r) {
		switch (true) {
		case null === $r  : return array();
		case is_string($r): return array((ACL::ACC_ALL == $r) ? array($r) : explode('/', $r));
		case is_array($r) :
			$a = array();
			foreach ($r as $path)
				$a[] = explode('/', $path);
			return $a;
		default           : throw new Exception('err_aclalowrec');
		}
	}

	function ACLToString($acl) {
		switch (true) {
		case $acl == ACL::ACL_GUEST  : return Loc::lget('acl_guest');
		case $acl == ACL::ACL_USER   : return Loc::lget('acl_user');
		case $acl == ACL::ACL_ADMINS : return Loc::lget('acl_admin');
		case $acl == ACL::ACL_MODER  : return Loc::lget('acl_moder');
		case $acl == ACL::ACL_BANNED : return Loc::lget('acl_banned');
		default                      : return Loc::lget('acl_error');
		}
	}