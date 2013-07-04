<?php
	$e = isset($this->errors) ? '<br /><span style="color: red">Неправильный пароль или электронный адрес</span>' : '';
	$url = ($url = post('url')) ? '?url=' . addslashes($url) : '';
?>

				<form class="register" action="/user/login<?=$url?>" method="POST">
					<br /><input type="text" name="login" value="<?=addslashes(post('login'))?>" /><label>Електронная почта</label><br /><br />
					<input type="password" name="pass" value="<?=addslashes(post('pass'))?>" /><label>Пароль</label><br /><br />
					<input type="submit" value="Вхoд" /><label><a href="/user/restore">Забыли пароль?</a></label>
				</form>
				<?=$e?>
