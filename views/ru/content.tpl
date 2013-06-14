	function content_meta() {
		$o = msqlDB::o();
		$s = $o->select('meta', '', '`name` as `0`, `content` as `1`');
		$r = $o->fetchrows($s);
		$meta = array();

		if (count($r)) {
			foreach ($r as $m)
				$meta[] = '	<meta name="' . $m[0] . '" content="' . $m[1] . '" />';
			return join(PHP_EOL, $meta) . PHP_EOL;
		}
		return null;
	}

	function content_user($view) {
		$user = User::get();
		$profile = $user->getLink('Профиль (' . $user->readable(true) . ')');
		switch (User::ACL()) {
		case ACL::ACL_USER:
			return $profile . '<a href="/user/logout">Выхoд</a>';
		case ACL::ACL_ADMINS:
			return str_replace(
				array('{%profile}', '{%url}')
			, array($profile, ($u = $_SERVER[REQUEST_URI]) ? '&url=' . $u : '')
			, @file_get_contents(View::findTPL('ap-drop', true))
			) . '<a href="/user/logout">Выход</a>';
		default:
			return '<a noindex nofollow href="/user/login">Вход</a><a noindex nofollow href="/user/registration">Регистрация</a>';
		}
	}

	function content_utils($view) {
		$l = '<script src="/theme/js/utils.js"></script>';

		switch (User::ACL()) {
		case ACL::ACL_USER:
			break;
		case ACL::ACL_MODER:
		case ACL::ACL_ADMINS:
			$l .= '<script src="/theme/admin/utils.js"></script>';
			break;
		default:
		}
		return $l . PHP_EOL;
	}
