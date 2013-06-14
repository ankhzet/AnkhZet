<?php
	header("HTTP/1.0 404 Not Found");
	$r = $this->request->getList();
	if ($r[0] == '404') array_shift($r);

	$file = '/' . htmlspecialchars(join('/', $r));
	$admin = FrontEnd::getInstance()->get('config')->get('site-admin');
?>
<div style="font-size:12px; font-family:Verdana, Geneva, sans-serif; padding: 2%; overflow: hidden;width: 96%;">
	&#187; &nbsp;Вы попытались получить доступ к файлу <i><?echo $file?></i>, который, к сожалению, отсутствует на сервере.<br />
	&#187; &nbsp;Возможно, вы ошиблись при указании имени файла, или он был перемещен или удален администратором сайта.<br />
	<div style="margin-top: 15px; padding-top: 5px; border-top: 1px dashed #bbb; font-size: 12px; color: #888;">
		Если вы попали на эту страницу последовав по ссылке на нашем сайте, пожалуйста, сообщите об этом <a href="mailto:<?echo $admin?>">администратору</a> сайта.<br />
		Если вы набирали адрес вручную - проверьте правильность ввода, нет ли в имени файла лишних символов и т.п.<br />
		Также возможно, что файл был переименован администратором, в таком случае вам следует перейти в соответствующий раздел сайта и проверить, не изменилась ли ссылка на файл.
	</div>
</div>