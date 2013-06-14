<?
	define(SUB_DOMEN, dirname(dirname(__FILE__)));

	require_once dirname(SUB_DOMEN) . '/www/_engine/common.php';
	require_once dirname(SUB_DOMEN) . '/www/_engine/captcha.php';

	$uid = stripslashes($_REQUEST['rid']);
	$c = new Captcha($uid);
	if (isset($uid) && $uid) {
	} else {
		$c->generate();
	}
	$c->image();
?>