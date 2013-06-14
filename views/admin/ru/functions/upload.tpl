<?php
	$a = array('title', 'content');
	foreach ($a as $key)
		${$key} = addslashes($_REQUEST[$key]);
?>
<form id="upload" class="long" style="height: 100px;" enctype="multipart/form-data" action="/share/upload" method=post>
	<h3>Загрузка файлов</h3>
	<input type=hidden name=action value="upload" />
	<div>
		<label for=icontent>Путь к файлу</label>
		<input id=icontent type="file" name="file" />
	</div>
	<div>
		<label for=isubmit disabled></label>
		<input type="submit" value="Загрузить" />
	</div>
</form>
<?php
	if ($id) {
		echo '<center>';
		$this->renderButton('Назад', '/share');
		echo '</center>';
	}
?>
