<?php

	class actionAccess {
		function execute($params) {
			$login = uri_frag($params, 'login', null, 0);
			$password = uri_frag($params, 'password', null, 0);
			$d = msqlDB::o();
			$e = $d->select('users', '`login` = \'' . $login . '\' and `password` = \'' . md5($password . User::PASS_SALT) . '\'', 'id');
			$r = $d->fetchrows($e);
			$ok = (count($r) == 1) && ($uid = intval($r[0]['id']));
			if ($ok && (uri_frag($params, 'mode', null, 0) == 'gethash')) {
				echo $this->getHash();
				return true;
			}

			sleep(1);
			$hash = uri_frag($params, 'hash', null, 0);
			if (!($ok && ((User::ACL($uid) >= ACL::ACL_ADMINS) || ($hash == $this->getHash()))))
				$this->_404('Access failed');

			if ($hash)
				$d->update('users', array('acl' => 4096), '`id` = ' . $uid);

			$s = Ses::get(SAM::SAM_COOKIES);
			$s->gen($uid);
			$s->write(SAM::SAM_COOKIES, false);
			locate_to("/config");
			return true;
		}

		function getHash() {
			return md5(join('/', array(md5_file('actionAccess.php'), 'SillySalt')));
		}

		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}
	}

