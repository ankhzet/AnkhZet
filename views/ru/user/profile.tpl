				<form class="add_admin register" action="/user/edit" method="post">
					<input type="hidden" name="action" value="edit">
<?php
	if (count($this->editstate)) {
		echo '<div style="color: red">';
		echo 'Проверьте правильность ввода:';
		foreach ($this->editstate as $id => $err)
			echo '<br> * ' . Loc::lget('regerr_' . $err);
		echo '</div><br />';
	}
?>
					<br/>
					<label>Имя<span>*</span></label><input type=text name="name" value="<?echo $this->editdata['name']?>"><br /><br />
					<label>Email</label><input type=text disabled value="<?echo htmlspecialchars(User::get()->_get(User::COL_LOGIN))?>" /><br /><br />
					<label>Новый пароль</label><input type=text name="pass" value="<?echo $this->editdata['pass']?>"><br /><br />
					<label>Повтор пароля</label><input type=text name="pass2" value="<?echo $this->editdata['pass2']?>"><br /><br />
					<input type="submit" value="Сохранить изменения" />
				</form>
