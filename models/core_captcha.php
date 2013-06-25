<?
	define(SUB_DOMEN, dirname(dirname(__FILE__)));

	require_once SUB_DOMEN . '/_engine/common.php';
	require_once SUB_DOMEN . '/_engine/captcha.php';

	$uid = stripslashes($_REQUEST['rid']);
	$c = new Captcha($uid);
	if (isset($uid) && $uid) {
	} else {
		$c->generate();
	}
	$c->image();
?>