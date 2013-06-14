<?php
	$id = $this->id;
	$a = array('title', 'source', 'content', 'preview');
	foreach ($a as $key)
		${$key} = htmlspecialchars(stripslashes($_REQUEST[$key]));

	if ($preview && (strpos($preview, '/') === false))
		$prev = '/data/news/' . $preview;
	else
		$prev = $preview;

	function label($view, $key, $caption) {
		$e = $view->errors[$key] ? ' style="color: red;"' : '';
		echo "<label for=\"i$key\"$e>$caption</label>";
	}
?>
			<iframe name='logo-iframe' style='display: none;'></iframe>
			<form class="cont" action="/models/core_logomaker.php?width=310" enctype="multipart/form-data" method=post target='logo-iframe'>
				<input type=hidden name=name value="preview_<?=rand(1, 9999)?>" />
				<input type=file accept="image/*" id=ilogo name=img />
				<?label($this, 'logo', 'Изображение')?><br /><br />
				<img src="<?= $prev ? $prev . '?rnd' . rand(1, 9999) : ''?>" alt="" />
			</form>
			<form class="cont" action="/news/add" enctype="multipart/form-data" method=post>
				<input type=hidden name=action value="add" />
				<input type=hidden name=id value="<?=$id?>" />
				<input type=hidden name=preview value="<?=$preview?>" />
				<input id=iname type="text" name="title" value="<?=$title?>" />
				<?label($this, 'title', 'Заголовок новости')?><br /><br />
				<input id=isource type="text" name="source" value="<?=$source?>" />
				<?label($this, 'source', 'Источник')?><br /><br />
				<textarea rows=20 cols=60 id=icontent name=content><?=$content?></textarea>
				<?label($this, 'content', 'Содержание')?><br /><br />
				<input type="submit" value="<?=$id?'Изменить':'Добавить'?>" />
			</form>
