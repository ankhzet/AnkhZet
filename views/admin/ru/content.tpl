	function content_loginform($view) {
	require_once "localization.php";
	require_once "dbengine.php";
	$u   = User::get();
	$acl = $u->ACL();
	if ($acl) {
		$login = $u->Login();
		return Loc::lget('welcome') . ', <a href="/user/' . $login . '">' . $login . '</a>. &nbsp;' .
			$view->renderButton(Loc::lget('logout'), '/user/logout', true, false);
	} else {
?>
					<form style="float: right; display: inline" action="/user/login?url=admin" method="POST">
						<input type="text" name="login" value="root" />
						<input type="password" name="pass" value="root" />
						<?php $view->renderButton(Loc::lget('login'), 'document.forms[0].submit()', false); ?>
					</form>
<?php
	}
	}

	function content_meta() {
		$o = msqlDB::o();
		$s = $o->select('meta', '', '`name` as `0`, `content` as `1`');
		$r = $o->fetchrows($s);
		$meta = array();

		if (count($r)) {
			foreach ($r as $m)
				$meta[] = '	<meta name="' . $m[0] . '" content="' . $m[1] . '" />';
			return join(PHP_EOL, $meta);
		}
		return null;
	}
