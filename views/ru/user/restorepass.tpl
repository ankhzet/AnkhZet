<?php
	$m = $this->restoredata[User::FLD_LOGIN];
	$u = $this->restoredata[unknown];
	if ($m || $u)
		$e = '<font color="red">' . Loc::lget('regerr_email') . '</font><br /><br />';
	else
		$e = '';
?>
			<form class="register" action="/user/restore">
				<input type=hidden name=action value="restore" />
				<label>Введите адрес електронной почты, что была указана при регистриции</label>
				<input type="text" name="email" value="<?echo $_REQUEST[email]?>" /><br /><br />
				<?=$e?>
				<input type="submit" value="Восстановить" />
			</form>
