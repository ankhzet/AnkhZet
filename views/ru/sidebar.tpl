	function sidebar_menu($view) {
		$uid = $view->ctl->user->ID();
		$last = $feed = '';
		$c = FrontEnd::getInstance()->get('config');
		$logo = $c->get('main.offline') ? 'offline' : 'logo';
		if ($uid) {
			$dbc= msqlDB::o();
			$s = $dbc->select('history as h, `pages` p', "`user` = $uid and `trace` = 1 and p.`id` = h.`page` and h.`size` <> p.`size`", 'count(h.`id`) as `0`');
			$c = $s ? intval(mysql_result($s, 0)) : 0;
			$last = $c ? "<div class=\"ui-updates green\"><div>$c</div></div>" : '';

			if ($view->ctl->userModer) {
				$s = $dbc->select('_mail', 'tag = 0', 'count(`id`) as `0`');
				$c = $s ? intval(mysql_result($s, 0)) : 0;
				$feed = $c ? "<a href=\"/feedbacks\" style=\"padding-left: 0;\"><div class=\"ui-updates green\"><div>$c</div></div></a>" : '';
			}
		}

		if (!$uid)
			return '
			<ul class="menu">
				<li><a href="/"><img src="/theme/img/logo.png" alt="Главная" title="Главная" /></a></li>
				<li><a href="/authors">Авторы</a></li>
				<li><a href="/feedback">Фидбэк</a></li>
				<li><a href="/about">О сайте</a></li>
			</ul>
';
		else
			return "
			<ul class=\"menu\">
				<li><a href=\"/\"><img src=\"/theme/img/{$logo}.png\" alt=\"Главная\" title=\"Главная\" /></a></li>
				<li><a href=\"/authors\">Авторы</a></li>
				<li><a href=\"/updates\">Обновления{$last}</a></li>
				<li><a href=\"/feedback\">Фидбэк</a>{$feed}</li>
				<li><a href=\"/about\">О сайте</a></li>
			</ul>
";
	}
