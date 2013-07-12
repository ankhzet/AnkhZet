<?php
	global $cf;
	$cf = FrontEnd::getInstance()->get('config');
	function getv($param) {
		global $cf;
		return htmlspecialchars($cf->get($param));
	}

	function genSelect($list, $sel, $keys = false) {
		$v = array();
		$r = array();
		foreach ($list as $key => $item) {
			if (strpos($key, '=') !== false) {
				$keyp = explode('=', $key);
				$keyp = $keyp[0];
			} else
				$keyp = $key;

			$_key = str_pad(abs($keyp), 2, '0', STR_PAD_LEFT);
			$s = ($key == $sel) ? ' selected="selected"' : '';
			if ($keys)
				$_key = (($keyp >= 0) ? "+$_key" : "-$_key") . ' ';
			else
				$_key = '';

			$r[$keyp] = true;
			$v[] = "<option value=\"$key\"{$s}>{$_key}$item</option>";
		}

		return join('', $v);
	}
?>

<div id=config>
	<form action="/config" method="post">
		<input type=hidden name=action value=save />

		<h3><span></span>Сайт</h3>
		<div><label>Главный контроллер:</label><input type=text name="main[main-controller]" value="<?echo getv('main.main-controller')?>" /></div>
		<div><label>Название сайта:</label><input type=text name="main[site-title]" value="<?echo getv('main.site-title')?>" /></div>
		<div><label>e-mail администратора:</label><input type=text name="main[site-admin]" value="<?echo getv('main.site-admin')?>" /></div>
		<div><label>e-mail нотификатор:</label><input type=text name="main[mail-notifier]" value="<?echo getv('main.mail-notifier')?>" /></div>
		<div>
			<label>Временная зона:</label>
			<select name="main[time-zone]"><?=genSelect($this->zones, getv('main.time-zone'))?></select>
		</div>
		<div>
			<label>&nbsp;</label>
			<select name="main[time-offset]"><?=genSelect($this->offsets, getv('main.time-offset'), true)?></select>
		</div>

		<h3><span></span>Настройки подключения к БД MySQL</h3>
		<div><label>Имя БД:</label><input type=text name="db[dbname]" value="<?echo getv('db.dbname')?>" /></div>
		<div><label>Хост:</label><input type=text name="db[host]" value="<?echo getv('db.host')?>" /></div>
		<div><label>Пользователь:</label><input type=text name="db[login]" value="<?echo getv('db.login')?>" /></div>
		<div><label>Пароль:</label><input type=text name="db[password]" value="<?echo getv('db.password')?>" /></div>
		<div><label>Отладочный режим:</label><input type=checkbox name="db[debug]" <?echo getv('db.debug') ? 'checked ' : ''?> /></div>

		<h3><span></span>Инициализация</h3>
		<div><label></label><a href="/config/init">Инициализация сайта</a></div>

		<h3></h3>
		<div><label></label><input type=submit value=" Сохранить " /></div>
	</form>
</div>