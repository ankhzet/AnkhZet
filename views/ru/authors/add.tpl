<?php
	$id = $this->id;
	$moder_link = ($this->ctl->userModer && $id) ? '<span>[<a href="/authors/delete/' . $id . '">удалить</a>]</span><br />' : '';
	$a = array('fio');
	foreach ($a as $key)
		${$key} = htmlspecialchars($_REQUEST[$key]);

	function error($view, $key) {
		if ($view->errors[$key])
			echo ' style="color: red;"';
	}
?>
	<div class="text">
		<form action="/authors/add" method=post>
			<input type=hidden name=action value="add" />
			<input type=hidden name=id value="<?echo $id?>" />
			<input id=ilink type="text" name="link" value="<?=$link?>" /><label for=ilink<?error($this, 'link')?>>URL</label><br /><br />
			<input id=iname type="text" name="fio" value="<?=$fio?>" /><label for=iname<?error($this, 'fio')?>>FIO</label><br /><br />
			<input type="submit" value="Добавить/изменить" /><label>&nbsp;</label>
		</form>
		<?echo $moder_link?>
	</div>