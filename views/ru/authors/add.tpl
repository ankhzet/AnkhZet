<?php
	$id = isset($this->id) ? $this->id : 0;
	$moder_link = ($this->ctl->userModer && $id) ? "<span>[<a href=\"/authors/delete/$id\">удалить</a>]</span><br />" : '&nbsp;';
	$a = array('fio', 'link');
	foreach ($a as $key)
		${$key} = htmlspecialchars(post($key));

	function error($view, $key) {
		if (uri_frag($view->errors, $key))
			echo ' style="color: red;"';
	}
?>
	<div class="text">
		<form action="/authors/add" method=post>
			<input type=hidden name=action value="add" />
			<input type=hidden name=id value="<?=$id?>" />
			<input id=ilink type="text" name="link" value="<?=$link?>" /><label for=ilink<?error($this, 'link')?>>URL</label><br /><br />
			<input id=iname type="text" name="fio" value="<?=$fio?>" /><label for=iname<?error($this, 'fio')?>>FIO</label><br /><br />

			<label><?=$moder_link?></label><input type="submit" value="Добавить/изменить" />
		</form>
	</div>