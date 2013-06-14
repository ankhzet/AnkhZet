<?php
	$e = $this->errors ? '<br /><span style="color: red">Неправильный пароль или электронный адрес</span>' : '';
?>

				<form class="register" action="/user/login<? echo ($url = $_REQUEST[url]) ? '?url=' . addslashes($url) : '' ?>" method="POST">
					<br /><input type="text" name="login" value="<? echo addslashes($_REQUEST['login'])?>" /><label>Електронная почта</label><br /><br />
					<input type="password" name="pass" value="<? echo addslashes($_REQUEST['pass'])?>" /><label>Пароль</label><br /><br />
					<input type="submit" value="Вхoд" /><label><a href="/user/restore">Забыли пароль?</a></label>
				</form>
				<?=$e?>
