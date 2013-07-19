<?php
	$e = isset($this->errors) ? '<br /><span style="color: red">Неправильный пароль или электронный адрес</span>' : '';
	$url = ($url = post('url')) ? '?url=' . addslashes($url) : '';
?>

				<form class="register" action="/user/login<?=$url?>" method="POST">
					<label>Електронная почта</label><input type="text" name="login" value="<?=addslashes(post('login'))?>" /><br /><br />
					<label>Пароль</label><input type="password" name="pass" value="<?=addslashes(post('pass'))?>" /><br /><br />
					<input type="submit" value="Вхoд" /><br /><br />

					<label><a href="/user/restore">Забыли пароль?</a></label>
				</form>
				<?=$e?>
