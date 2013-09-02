<?php
	$days = uri_frag($_REQUEST, 'days', 1);
	$bots = uri_frag($_REQUEST, 'bots', 0);
	$ip = uri_frag($_REQUEST, 'ip', '', 0);

?>
<form method="post" class="title content-header" style="overflow: hidden;">
	<h3 style="clear: both;">Соотношение переходы/пользователи, график переходов:</h3>
	<div style="float: left">
{%ctr%}
	</div>
	<img style="float: right; width: 600px; height: 400px;" src="/models/core_userstat.php<?=$this->ctl->link?>" />
	<div style="clear: both">
		<h3>Фильтрация:</h3><br />
		<select name="days">
			<option value="1"<?=$days==1?' selected':''?>>за 1 день</option>
			<option value="7"<?=$days==7?' selected':''?>>за последнюю неделю</option>
			<option value="14"<?=$days==14?' selected':''?>>за последние 2 недели</option>
			<option value="30"<?=$days==30?' selected':''?>>за последние 30 дней</option>
			<option value="92"<?=$days==92?' selected':''?>>за последние 3 месяца</option>
		</select>&nbsp;
		<input type=radio name=bots value=0 <?=$bots==0?'checked':''?> /> Все
		&nbsp; <input type=radio name=bots value=1 <?=$bots==1?'checked':''?> /> Без ботов
		&nbsp; <input type=radio name=bots value=2 <?=$bots==2?'checked':''?> /> Только боты
		&nbsp;
		<input type=text style="width: 115px" name=ip placeholder="IP" value="<?=$ip?>" />&nbsp;
		<input type=submit style="clear: none; margin: 5px 0;" />
	</div>
	<br />
</form>

<style>
.cnt-item {
	overflow: hidden;
}
</style>
<?echo $this->data?>
<?echo $this->pages?>
