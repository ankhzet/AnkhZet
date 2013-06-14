<?php
	$id = $this->id;
	$a = array('title', 'link');
	foreach ($a as $key)
		${$key} = stripslashes($_REQUEST[$key]);

	function error($view, $key) {
		if ($view->errors[$key])
			echo ' style="color: red;"';
	}
?>
					<div class="static_head">Добавление статика</div>
					<form class="add_admin" action="/statics/add" enctype="multipart/form-data" method=post>
						<input type=hidden name=action value="add" />
						<input type=hidden name=id value="<?echo $id?>" />
						<label for=iname<?error($this, 'title')?>>Заголовок</label>
						<input id=iname type="text" name="title" value="<?echo htmlspecialchars($title)?>" onchange="translate()" /><br /><br />
						<label for=ilink<?error($this, 'link')?>>Ссылка</label>
						<input id=ilink type="text" name="link" value="<?echo htmlspecialchars($link)?>" /><br /><br />
						<?if ($id) echo '<a href="/templates/' . Loc::Locale() . '/' . $link . '?back=statics/edit/' . $link . '">Править страницу в WYSWYG редакторе</a><br/><br />';?>
						<input type="submit" value="" />
					</form>
