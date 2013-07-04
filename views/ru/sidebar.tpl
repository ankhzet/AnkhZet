	function sidebar_menu($view) {
		$uid = $view->ctl->user->ID();
		if ($uid) {
			require_once 'core_history.php';
			$s = msqlDB::o()->select('history as h, `pages` p', "`user` = $uid and `trace` = 1 and p.`id` = h.`page` and h.`size` <> p.`size`", 'count(h.`id`) as `0`');
			$c = $s ? intval(mysql_result($s, 0)) : 0;
			$last = $c ? "<div class=\"ui-updates green\"><div>$c</div></div>" : '';
		}

		if (!$uid)
			return '
			<ul class="menu">
				<li><a href="/">Главная</a></li>
				<li><a href="/about">О сайте</a></li>
			</ul>
';
		else
			return "
			<ul class=\"menu\">
				<li><a href=\"/\">Главная</a></li>
				<li><a href=\"/authors\">Авторы</a></li>
				<li><a href=\"/updates\">Обновления{$last}</a></li>
				<li><a href=\"/about\">О сайте</a></li>
			</ul>
";
	}
