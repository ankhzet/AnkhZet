<?php

	function allow_route(&$routes, $route, $uid, $allow = true) {
		if (!$routes[$route])
			$routes[$route] = array();
		$routes[$route][$uid] = $allow;
	}

	function normalize_uri($uri) {
		$uri = preg_replace('/[\\\\\/]{2,}|\\\\/', '/', $uri);
		preg_match('/(.+?)($|\/?\*)/i', $uri, $m);
		return $m[1];
	}

	class ACLController extends AdminViewController {
		protected $_name = 'acl';

		var $classes = array();

		function listActions($c) {
			if (!is_file($f = ROOT . '/controllers/' . $c))
				$f = ENGINE_ROOT . '/' . $c;

			$c = htmlspecialchars(file_get_contents($f));
			$c = preg_replace('/\/\*([^\*]+\*?)\*\//', '', $c);
			$c = preg_replace('/\/\/[^\r\n]*/', '', $c);

			$c = preg_replace('/((function|class|extends)\s*[\w_\d]*\s*(\([^\)\{]*\))?\s*)\{/i', '\\1==**==', $c);
			do {
			$c1 = $c;
			$c = preg_replace('/\{[^\{\}]*\}/', '', $c1);
			}while ($c != $c1);
			$c = str_replace(array('	', '==**=='), array(' ', '{'), $c);
			$c = preg_replace('/\{[^\{\}]*\}/', '', $c);
			$c = preg_replace('/\([^\(\)]*\)/', '()', $c);
			$c = preg_replace('/(protected|private|public)/', '', $c);
			$c = preg_replace('/[\8\40]{2,}/', ' ', $c);

			preg_match_all('/(class ([\w\d_]+)[^\}]*\})/i', $c, &$c);

			$m = array();
			foreach ($c[0] as $code) {
				preg_match('/class ([\w\d_]+)( extends ([\w\d_]+))?/i', $code, $e);
				$name = strtolower($e[1]);
				$exten = strtolower($e[3]);

				$_ = array('name' => $name, 'extends' => $exten, 'loaded' => true, 'actions' => array());
				if ($exten) {
					$e = $this->classes[$exten];
					$_['loaded'] = $e && $_['loaded'];
					if ($_['loaded'])
						$_['actions'] = $e['actions'];
				}

				preg_match_all('/function action([\w\d_]+)/i', $code, &$e);
				$_['actions'] = array_unique(array_merge($_['actions'], is_array($e[1]) ? $e[1] : array()));

				$this->classes[$name] = $_;
				$m[] = &$this->classes[$name];
			}
			$l = true;
			foreach ($m as &$class)
				$l &= $class['loaded'];

			return $l;
		}

		public function actionMain($r) {
			$c = Config::read('INI', '_engine/config.ini');
			$acl = $c->get(acl);
			$routing = $c->get(routing);
			$g = array();
			foreach ($acl as $group => $gacls)
				$g[$group] = intval($gacls[id]);

			asort($g, SORT_NUMERIC);

/*			$o = array();
			$dir = ROOT . '/controllers';
			$d = @dir($dir);
			if ($d)
				while (($entry = $d->read()) !== false)
					if (is_file($dir . '/' . $entry) && preg_match('/controller\.php/i', $entry))
						$o[] = $entry;
			$reread = 5;
			$o[] = 'controller.php';
			$o[] = 'AggregatorController.php';
			do {
				$m = array();
				foreach ($o as $ctl)
					if (!$m[$ctl])
						$m[$ctl] = $this->listActions($ctl);

			} while (--$reread > 0);

			foreach ($this->classes as $ctl => &$class)
				if (!$class['loaded'] || !count($class['actions']))
					unset($this->classes[$ctl]);

			$o = array();
			foreach ($this->classes as $ctl => $class) {
				preg_match('/([\w\d_]+)controller/i', $ctl, &$m);
				sort($class['actions']);
				$o[$m[1]] = array('extends' => $class['extends'], 'actions' => $class['actions']);
				unset($this->classes[$ctl]);
			}
			ksort($o);

			$t = array();
			foreach ($o as $ctl => &$class) {
				$h = '<tr><td colspan=4>' . ucfirst($ctl) . 'Controller (' . ucfirst($class['extends']) . ') </td></tr>';
				foreach ($class['actions'] as $action) {
					$u = '';
					foreach ($g as $group)
						$u .= '<td>' . ACL::allowed($group, $ctl . '/' . strtolower($action) . '/') . '</td>';

					$h .= '<tr><td>' . $action . '</td>' . $u . '</tr>';
				}
				$t[] = $h;
			}

			echo '<table>' . join('', $t) . '</table>';*/

			switch ($_REQUEST[action]) {
			case modify:
				$routes = $_REQUEST[routes];
				asort($routes);
				$_acls = $_REQUEST[route];

				$acls = array();
				$rr = array();
				foreach ($routes as $rid => $route) {
					$r1 = explode('/', $route);
					if ($r2 = $r1[0])
						$rr[$r2] = $routing[$r2] ? $routing[$r2] : $r2;

					foreach ($_acls[$rid] as $id => $_acl) {
						if (!(intval($_acl) - 1))
							$acls[$id][] = ($route ? $route . '/' : '') . ACL::ACC_ALL;
					}
				}

				foreach ($g as $group => $id) {
					if (array_search(ACL::ACC_ALL, is_array($acls[$id]) ? $acls[$id] : array($acls[$id])) === false)
						$c->set(array(acl, $group, allow), ACL::ACC_ALL);

					$c->set(array(acl, $group, disallow), $acls[$id]);
				}

				ksort($rr);
				$c->set(routing, $rr);

				debug($routes, 'routes');
				debug($_acls, '_acls');
				debug($acls, 'acls');
				debug($rr, 'routing');
				$c->save();
				header('Location: /acl');
				break;
			case add:
				$_route = normalize_uri(stripslashes($_REQUEST[route]));
				$route = $_route . '/*';

				$_routed = normalize_uri(stripslashes($_REQUEST[redir]));
				if (!$_routed) $_routed = $_route;
				$redir = $_routed . '/*';

				$d = $c->get(array(acl, 'guest', disallow));
				if (array_search($route, $d) === false) array_push($d, $route);
				$c->set(array(acl, 'guest', disallow), $d);
				if (array_search($route, $_route) === false) {
					$routing[$_route] = $_routed;
					$c->set(routing, $routing);
				}
				$c->save();
				header('Location: /acl');
			case del:
				$route = normalize_uri(stripslashes($_REQUEST[uroute]));
				unset($routing[$route]);
				$c->set(routing, $routing);
				$c->save();
				header('Location: /acl');
			case group:
				$group = $_REQUEST[group];
				$aid = $g[ACL::ACL_USER];
				while (array_search($aid, $g)) $aid++;

				$c->set(array(acl, $group), array(id => $aid, disallow => '*'));
				$c->save();
				header('Location: /acl');
			}

			$r = array();
			foreach ($acl as $group => $acls) {
				if (is_array($acls[disallow]) && count($acls[disallow]))
					foreach ($acls[disallow] as $route)
						allow_route(&$r, $route, $id, false);
				else
					if ($route = trim($acls[disallow]))
						allow_route(&$r, $route, $id, false);
			}

			$t = array();
			foreach ($g as $group => $id)
				$t[] = '<td class="headers">' . $group . '<br/>ACL#' . sprintf('%04X', $id) . '</td>';

			define(_CHK_, ' checked');
			$p  = '<td><input class="dis" type=radio name="route[{%route}][{%group}]"{%0} value=1 /><input class="all" type=radio name="route[{%route}][{%group}]"{%1} value=2 /></td>';
			$p3 = '<tr{%odd}><td width=16><a class="del" href="/acl?action=del&uroute={%id}"></a></td><td class="route">{%route}</td><td class="route">{%redir}</td>{%acls}</tr>';
			$o  = array();
			$e = array();
			$routes = array();
			asort($routing);
			foreach ($routing as $route => $redirect) {
				$w = array();
				$row = count($e);
				$uri = normalize_uri($route);
				$rid = $routes[$uri];
				if (!$rid) $routes[$uri] = $rid = count($routes);
				foreach ($g as $group => $id) {
					$v = (ACL::allowed($id, $redirect));
					$u = array(
						'route' => $rid
					, 'group' => $id
					, '0' => !$v ? _CHK_ : ''
					, '1' =>  $v ? _CHK_ : ''
					);
					$w[$id] = patternize($p, $u);
				}
				$u = array(id => urlencode($uri), 'odd' => $row % 2 ? ' class="odd"' : '', route => $route, redir => $redirect, acls => join('', $w));
				$e[$route] = patternize($p3, $u);
			}
			foreach ($r as $route => $acls) {
				$w = array();
				$row = count($o);
				$uri = normalize_uri($route);
				if (count(explode('/', $uri)) <= 1) continue;
				$rid = $routes[$uri];
				if (!$rid) $routes[$uri] = $rid = count($routes);

				foreach ($g as $group => $id) {
					$v = (ACL::allowed($id, str_replace('*', '', $route)));
					$u = array(
						'route' => $rid
					, 'group' => $id
					, '0' => !$v ? _CHK_ : ''
					, '1' =>  $v ? _CHK_ : ''
					);
					$w[$id] = patternize($p, $u);
				}
				$o[$route] = '<tr class="' . ($row % 2 ? 'odd' : '') . '"><td class="route">' . $route . '</td>' . join('', $w) . '</tr>';
			}

			$uroutes = array();
			asort($routes);
			foreach ($routes as $route => $id)
				$uroutes[] = '<input type=hidden name="routes[' . $id . ']" value="' . htmlspecialchars($route) . '" />';

?>
	<div id="config">
		<form action="/acl" method="post">
			<h3><span></span>Уровни доступа групп пользователей</h3>
			<input type=hidden name=action value=modify />
	<?=join(PHP_EOL . "\t", $uroutes)?>

			<div id="dtcontainer">
				<table id="datatable">
					<tr class="header"><td></td><td>Путь</td><td>Редирект</td><?=join('', $t)?></tr>
					<tr><?=join(PHP_EOL, $e)?></tr>
				</table>
				<table id="datatable">
					<tr class="header"><td>URI</td><?=join('', $t)?></tr>
					<tr><?=join(PHP_EOL, $o)?></tr>
				</table>
			</div>
			<div><label></label><input type=submit value=" Изменить " /></div>
		</form>
		<form action="/acl" method="post">
			<h3><span></span>Роутинг</h3>
			<input type=hidden name=action value=add />
			<div><label>Новый маршрут:</label><input type=text name=route value="<?=htmlspecialchars(stripslashes($_REQUEST[route]))?>" /></div>
			<div><label>Редирект:</label><input type=text name=redir value="<?=htmlspecialchars(stripslashes($_REQUEST[redir]))?>" /></div>
			<div><label></label><input type=submit value=" Добавить маршрут " /></div>
		</form>
		<form action="/acl" method="post">
			<h3><span></span>Добавление групп пользователей</h3>
			<input type=hidden name=action value=group />
			<div><label>Новая группа пользователей:</label><input type=text name=group value="<?=htmlspecialchars(stripslashes($_REQUEST[group]))?>" /></div>
			<div><label></label><input type=submit value=" Добавить группу " /></div>
		</form>
	</div>
	<br />
		<?php
		}
	}
?>