	function sidebar_menu() {
		if (User::ACL() == ACL::ACL_GUEST)
			return '
			<ul class="menu">
				<li><a href="/">Главная</a></li>
				<li><a href="/about">О сайте</a></li>
			</ul>
';

		else
			return '
			<ul class="menu">
				<li><a href="/">Главная</a></li>
				<li><a href="/authors">Авторы</a></li>
				<li><a href="/updates">Обновления</a></li>
				<li><a href="/about">О сайте</a></li>
			</ul>
';
	}
