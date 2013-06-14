<span class="ap-admin">
	<a href="#">Админка</a>
	<div class="ap-drop">
		<ul>
			{%profile}
			<li>&#187; <a href="/admin">Админ-функции</a></li>
			<li>&#187; <a href="/statics">Статики</a></li>
			<li>&#187; <a href="/feedbacks">Фидбеки</a></li>
			<li>&#187; <a href="/user/logout">Виход</a></li>
			<li>&nbsp;</li>
			<li>&#187; <a href="/api.php?action=clearcache{%url}">Очистить кеш шаблона</a></li>
			<li>&#187; <a href="/api.php?action=clearthumbscache">Очистить кеш превью</a></li>
			<li>&#187; <a href="/api.php?action=toggleprofiler">Включить/выключить профайлер</a></li>
		</ul>
	</div>
</span>