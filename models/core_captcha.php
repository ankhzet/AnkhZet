<?
	require_once '../base.php';
	require_once ENGINE_ROOT . '/captcha.php';

	$uid = stripslashes($_REQUEST['rid']);
	$c = new Captcha($uid);
	if (isset($uid) && $uid) {
	} else {
		$c->generate();
	}
	$c->image();
?>