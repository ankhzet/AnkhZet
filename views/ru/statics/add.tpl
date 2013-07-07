<?php
	$id = $this->id;
	$a = array('title', 'link');
	foreach ($a as $key)
		${$key} = stripslashes($_REQUEST[$key]);

	function error($view, $key) {
		if ($view->errors[$key])
			echo ' style="color: red;"';
	}

	$l = $id
	? '<a href="/templates/' . Loc::Locale() . '/' . $link . '?back=statics/edit/' . $link . '">Править страницу в WYSWYG редакторе</a><br/><br />'
	: '&nbsp;';
?>
					<form class="add_admin" action="/statics/add" enctype="multipart/form-data" method=post>
						<input type=hidden name=action value="add" />
						<input type=hidden name=id value="<?echo $id?>" />
						<label for=iname<?error($this, 'title')?>>Заголовок</label>
						<input id=iname type="text" name="title" value="<?echo htmlspecialchars($title)?>" onchange="translate()" /><br /><br />
						<label for=ilink<?error($this, 'link')?>>Ссылка</label>
						<input id=ilink type="text" name="link" value="<?echo htmlspecialchars($link)?>" /><br /><br />
						<label><?=$l?></label>
						<input type="submit" value="Сохранить" />
					</form>
