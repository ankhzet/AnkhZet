<?php
	$a = array('name', 'contact', 'msg');
	foreach ($a as $key)
		${$key} = stripslashes(htmlspecialchars($_REQUEST[$key]));

	global $errs;
	$errs = $this->errors;

	function err($field, $title) {
		global $errs;
		echo ($errs[$field])
			? '<label for="i' . $field . '" style="color: red;">' . $title . ':</label>'
			: '<label for="i' . $field . '">' . $title . ':</label>';
	}

	if (!$name) {
		$u = User::get();
		if ($u->ID())
			$name = $u->readable();
	}

	require_once 'captcha.php';
	$c = new Captcha();
	$rid = $c->generate();
?>
	<form class="upload" action="/feedback/send" method="POST">
		<input type=hidden name=rid value="<?=$rid?>" />
		<?err('name', 'Ваши имя и фамилия')?> <input type="text" name="name" value="<?=$name?>" /><br/><br/>
		<?err('contact', 'Ваши контакты')?><textarea name="contact"><?=$contact?></textarea><br/><br/>
		<?err('msg', 'Текст сообщения')?><textarea name="msg"><?=$msg?></textarea><br/><br/>
		<input id=icaptcha type=text class="captcha" name="captcha" /><?=err('captcha', 'CAPTCHA')?>
		<br /><br />
		<label>&nbsp;</label><img class="captcha-img" src="/models/core_captcha.php?rid=<?=$rid?>" alt="" />
		<br /><br />
		<label>&nbsp;</label><input type="submit" value="Отправить" />
	</form>
