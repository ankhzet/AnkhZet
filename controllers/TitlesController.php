<?php

	function build_loc(&$l, $ll, $prefix = '') {
		$patt = '<div><label><a class="del" href="?action=del&key={%key}"></a>{%key}</label><input type=text name="loc[{%key}]" value="{%str}" /></div>';
		$a = array();
		foreach ($ll as $key => $locstr)
			if (is_array($locstr))
				build_loc(&$a, $locstr, $prefix . $key . '.');
			else {
				$v = array('key' => $prefix . $key, 'str' => htmlspecialchars($locstr), 'odd' => (count($l) % 2) ? '' : ' class="odd"');
				$a[] = patternize($patt, &$v);
			}
		if (count($a))
			if ($prefix)
				$l[] = '<div class="fold"><span onclick="fold(this)">' . $prefix . '* [+/-]</span><div>' . join(PHP_EOL, $a) . '</div></div>';
			else
				$l[] = join(PHP_EOL, $a);
	}

	class TitlesController extends AdminViewController {
		protected $_name = 'titles';

		public function actionLocale($r) {
			$log = @file_get_contents(ROOT . '/locale.log');
			$log = $log ? unserialize($log) : array();

			$l = array();
			if (count($log)) {
				$e = array();
				$n = array();
				foreach ($log as $entry => $stat)
					if ($entry == $stat['localization'])
						$e[$entry] = $stat;
					else
						$n[$entry] = $stat;
				ksort($e);
				ksort($n);

				foreach ($e as $entry => $stat) {
					$t = str_replace(array('admin', 'main'), '', $entry);
					$r = $n[$t] ? ' <span style="color: #080;">[' . $n[$t]['localization'] . ' ?]</span>' : '';
					$l[] = '<li style="color: red;"><span style="width: 200px; display: inline-block; font-weight: bold;">' . $entry . '</span> => ' . $stat['localization'] . $r . ' (' . $stat['hits'] . ' hits)';
				}
				foreach ($n as $entry => $stat)
					$l[] = '<li><span style="width: 200px; display: inline-block; font-weight: bold;">' . $entry . '</span> => ' . $stat['localization'] . ' (' . $stat['hits'] . ' hits)';
			}

			echo '<ul>' . join('', $l) . '</ul>';
		}

		public function actionMain($r) {
			$c = Config::read('INI', 'cms://root/locale.ini');
			$lang = uri_frag($r, 0, null, 0);
			if (array_search($lang, Loc::$LOC_ALL) === false)
				$lang = Loc::Locale();
			$loc = $c->get($lang);
			ksort($loc);

			switch (post('action')) {
			case 'add':
				$page = stripslashes(post('page'));
				$title = str_replace('=', '&#61;', htmlspecialchars(post('title')));
				$c->set(array($lang, $page), $title);
				$c->save();
				locate_to("/{$this->_name}");
				break;
			}
			$l = array();
			build_loc($l, $loc);

			echo <<<here
<div id="config">
	<form action="/titles" method="post">
		<h3><span></span>Названия страниц</h3>
here;
			echo join('', $l);
			echo<<<here
		<div><label></label><input type=submit value=" Изменить " /></div>
	</form>
	<form action="/titles?action=add" method="post">
		<h3><span></span>Определить название</h3>
		<div><label>Страница</label><input type=text name="page" value="" /></div>
		<div><label>Название</label><input type=text name="title" value="" /></div>
		<div><label></label><input type=submit value=" Определить " /></div>
	</form>
</div>
	<br />
here;
		}
	}
?>