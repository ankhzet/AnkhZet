<?php
	require_once "link.php";
	require_once "localization.php";

	require_once 'table.php';

	function capitalize($str) {
		return strtoupper($str[0]) . substr($str, 1);
	}

	class UsersTableRenderer extends DBTableRenderer {
		const T_X        = '';
		const T_X2       = '-';
		const T_LOGIN    = User::COL_LOGIN;
		const T_ACL      = User::COL_ACL;
		var $user;

		function __construct($p, $link) {
			$this->params = $p;
			parent::__construct($link, 'users');
			$this->user = User::get()->_get(User::COL_LOGIN);
			$c = Config::read('INI', 'cms://config/config.ini');
			$this->acls = $c->get('acl');
		}

		function columns() {
			return array(self::T_X, self::T_LOGIN, self::T_ACL, self::T_X2);
		}

		function colWidths() {
			return array(self::T_X => '24px', self::T_LOGIN => 15, self::T_ACL => 20);
		}

		function colTitles() {
			return array(
					self::T_LOGIN => 'e-mail'
				, self::T_ACL   => 'ACL'
			);
		}

		function prepareRow($column, $data) {
			switch ($column) {
			case self::T_X:
				return '<a class="del" href="/admin/confirm/0/admin/kick/' . $data[self::T_ID] . '?return=admin/userlist"></a>';
			case self::T_LOGIN:
				$login = $data[$column];
				if ($this->user == $login)
					return '<b>' . $login . '</b>';
				else
					return $login;
			case self::T_ACL:
				return ACLToString(intval($data[$column]));
			case self::T_X2:
				$login = $data[self::T_LOGIN];
				$a = array();
				foreach ($this->acls as $acl => $acls)
					if (($id = $acls['id']) && ($id !== $data[self::T_ACL]))
						$a[] = '<a href="/admin/promote/?login=' . $login . '&acl=' . $id . '">' . $acl . '</a>';

				return 'Дать права доступа: ' . join(', ', $a) . '';
			default:
				return $data[$column];
			}
		}
	}

	class AdminController extends AdminViewController {
		protected $_name = 'admin';

		function userSelf($id) {
			return User::get()->_get(User::COL_ID) == $id;
		}

		function makeSub($link, $caption, $sub = false) {
			if ($sub)
				$this->view->subtitle = '<a href="/admin/' . $link . '">' . $caption . '</a> &rarr; ' . $sub;
			else
				$this->view->subtitle = '<a href="/admin/' . $link . '">' . $caption . '</a>';
		}

		public function actionMain($r) {
			require_once 'heartbeat.php';
			Heartbeat::pulse($this->user->ID());

			if (uri_frag($r, 0, 0, 0))
				if (preg_match('/^[a-z]+$/i', $r[0])) {
					$sub = Loc::lget('titles.admin' . $r[0]);
					if ($sub)
						$this->makeSub('', 'Админ-функции', $sub);
					return $this->view->renderTPL('functions/' . $r[0]);
				}

			$this->makeSub('', 'Админ-функции');
			$this->view->renderTPL('links');
		}

		public function actionReset($r) {
			$c = FrontEnd::getInstance()->get('config');
			$o = msqlDB::o();
			$o->query('drop database `' . $c->get('db.dbname') . '`');
			locate_to('/config');
		}

		public function actionConfirm($r) {
			$this->view->renderTPL('confirm');
		}

		public function actionPromote($r) {
			$this->makeSub('userlist', 'Список пользователей', 'Изменение уровня доступа пользователя');
			$a = $this->request->getArgs();
			$u = $a[login];
			$a = $a[acl];
			if (!$a) {
				$this->view->renderMessage('Логин не указан.', View::MSG_ERROR);
			} else
			if ($u == User::get()->Login()) {
				$this->view->renderMessage('Нельзя изменять собственный уровень доступа.', View::MSG_ERROR);
			} else {
				$db = msqlDB::o();
				$s = $db->select('users u', '`login` = \'' . $u . '\'', '`id`');
				if ($s && ($db->rows($s) == 1)) {
					$e = $db->fetchrows($s);
					$e = $e[0];
					$db->update('users', array('acl' => $a), array('id' => $e['id']));
					$this->view->renderMessage('Модификация уровня доступа [' . (new Tag_A(array('link' => 'user/' . $u, 'text'=> $u), false)) . '] завершена...', View::MSG_INFO);
				} else {
					$this->view->renderMessage('Пользователь "' . $u . '" не найден oO', View::MSG_WARN);
				}
			}
			$this->ctrButton('Назад', '/admin/userlist');
		}

		public function actionUserlist($r) {
			$this->makeSub('', 'Админ-функции', 'Список пользователей');
			$r = new UsersTableRenderer($this->request->getArgs(), '/admin/userlist/?');
			echo '<div id="dtcontainer">';
			$r->render();
			echo '</div>';
		}

		public function actionKick($r) {
			$this->makeSub('userlist', 'Список пользователей', 'Удаление пользователя');
			$id = uri_frag($r, 0);
			if ($this->userSelf($id))
				$this->view->renderMessage('Вы не можете удалить сами себя.', View::MSG_ERROR);
			else {
				$c = msqlDB::o();
				$s = $c->delete('users', '`id` = \'' . $id . '\'');
				if ($s)
					locate_to('/admin/userlist');
				else
					$this->view->renderMessage('Ошибка при удалении!', View::MSG_ERROR);
			}
			$this->ctrButton('Назад', '/admin/userlist');
		}

		public function actionTemplates($r) {
			if (strpos($_SERVER['REQUEST_URI'], '/admin/templates') !== false)
				locate_to('/templates');

			$lang = uri_frag($r, 0, Loc::LOC_RU, 0);
			$content = uri_frag($r, 1, null, 0);
			$dir = SUB_VIEWS . '/' . $lang . '/static/';
			$file = $dir . $content . '.tpl';
			if (file_exists($file)) {
				$this->editContents($lang, $file, $content);
				return;
			}
			$d = @dir($dir);
			if (!$d) {
				$lang = Loc::LOC_RU;
				echo '<p>[' . $r[0] . '] locale directory not found, using [' . $lang . '] instead...</p>';
				$dir = SUB_VIEWS . '/' . $lang . '/static/';
				$file = $dir . $content . '.tpl';
				if (file_exists($file)) {
					$this->editContents($lang, $file, $content);
					return;
				}
				$d = @dir($dir);
				if (!$d) {
					echo '<p>[' . $lang . '] locale directory not found aither!</p>';
					return;
				}
			}
			$this->makeSub('', 'Админ-функции', 'Редактирование шаблонов');
			echo '<div style="padding: 20px;">';
			echo 'Редактируемая локализация: <img src="/theme/img/locale/' . $lang . '.jpg" style="height: 12px;"><br>';
			if (count(Loc::$LOC_ALL) > 1) {
				echo 'Другие локализации:<ul style="display: inline;">' . PHP_EOL;
				foreach (Loc::$LOC_ALL as $loc)
					if ($loc != $lang) echo '<li style="display: inline;"><a href="/templates/' . $loc . '"><img src="/theme/img/locale/' . $loc . '.jpg" style="height: 12px;"></a></li>' . PHP_EOL;
				echo '</ul><br>' . PHP_EOL;
			}

			$t = array();
			$s = array();
			while (false !== ($entry = $d->read()))
				if (!is_dir($dir . $entry))
					if (preg_match('/^(.+)\.tpl$/i', $entry, $match)) {
						if (!isset($t[$filename = $match[1]]))
							$t[$filename] = true;

						$s[$filename] = @filesize($dir . $entry);
					}

			echo 'Доступные шаблоны:<br>';
			if (!count($t))
				echo ' &nbsp; Нет доступных шаблонов';
			else {
				echo '<ul class="templates">';
				foreach ($t as $tpl => $bool)
					echo '<li><span class="template"><a class="title" href="/templates/' . $lang . '/' . $tpl . '" title="Редактировать [' . $tpl . '.tpl]">' . capitalize($tpl) . ' (' . $tpl . '.tpl)</a><span class="fs">' . fs($s[$tpl]) . '</span></span></li>';
				echo '</ul>';
			}
			echo '</div>';
		}

		function editContents($lang, $file, $template) {
			$this->makeSub('templates/' . $lang, 'Редактирование шаблонов', 'Редактирование шаблона "' . $template . '"');
			$contents = post('editor');
			if (isset($contents)) {
				$contents = preg_replace('/((\.\.\/)+)/', $this->view->host . '/', stripslashes($contents));
				if (@file_put_contents($file, $contents) === false) {
					$this->view->renderMessage('Ошибка сохранения!', View::MSG_ERROR);
					return;
				}
			}
			require_once 'common.php';
			$contents = htmlspecialchars(file_get_contents($file));
			$tpl = @file_get_contents(dirname(dirname(__FILE__)) . '/views/editorinclude.tpl');
			$back = post('back');
			$data = array('host' => 'http://' . make_domen($_SERVER['HTTP_HOST'], 'tinymce'), 'lang' => $lang, 'template' => $template, 'contents' => $contents, 'back' => $back ? $back : 'templates/' . $lang);
			$out = patternize($tpl, $data);
			die($out);
		}

		public function actionShare($r) {
			$f1 = uri_frag($r, 1, '', 0);
			$f2 = uri_frag($r, 2, '', 0);
			$file = '/data/share/' . urldecode($f1) . ($f2 ? ".{$f2}" : '');
			switch (uri_frag($r, 0)) {
			case 'upload':
				if (post('action') == 'upload') {
					$r = fileLoad($_FILES['file'], '/data/share/');
					if ($r[0] < 0)
						throw new Exception('err_fileupload' . (-intval($r[0])));
					locate_to('/share');
				} else
					$this->view->renderTPL('functions/upload');
				break;
			case 'delete':
				$this->makeSub('/share', Loc::lget('titles.share'), "Просмотр файла [{$file}]");
				$filename = SUB_DOMEN . $file;
				if (!file_exists($filename))
					$filename = mb_convert_encoding($filename, 'CP-1251', 'UTF-8');
				if (file_exists($filename)) {
					if (!@unlink($filename))
						throw new Exception('Ошибка при удалении "' . $file . '"');
					locate_to('/share');
				} else
					throw new Exception("Файл \"{$file}\" не найден");
				break;
			case 'show':
				$this->makeSub('/share', Loc::lget('titles.share'), "Просмотр файла [{$file}]");
				$filename = SUB_DOMEN . $file;
				if (!file_exists($filename))
					$filename = mb_convert_encoding($filename, 'CP-1251', 'UTF-8');

				if (file_exists($filename)) {
					echo '<object style="width: 100%; height: 600px;" data="' . $file . '" type="application/' . $r[2] . '" width="719">Тип файла "' . $file . '" не поддерживается</object>';
				} else
					throw new Exception('Файл "' . $file . '" не найден');

				break;
			default:
				$dir = SUB_DOMEN . '/data/share/';
				$d = @dir($dir);
				$this->makeSub('', 'Админ-функции', Loc::lget('titles.share'));
				$t = array();
				$s = array();
				while (false !== ($entry = $d->read()))
					if (!is_dir($dir . $entry))  {
							$filename = $entry;
							$filename = mb_convert_encoding($filename, 'UTF-8', 'CP-1251, CP-1252');
							if (!isset($t[$filename]))
								$t[$filename] = true;

							$s[$filename] = @filesize($dir . $entry);
						}

				echo '<div style="padding: 20px;">';
				echo 'Доступные файлы:<br />';
				if (!count($t))
					echo ' &nbsp; Нет файлов';
				else {
					echo '<div><ul class="templates share">';
					foreach (array_keys($t) as $file) {
						$dot = mb_strrpos($file, '.');
						$name = mb_substr($file, 0, $dot);
//						$uname = mb_convert_encoding($name, 'CP-1251', 'UTF-8');
//						if ($name != $uname) $name = $uname;
						$ext = mb_substr($file, $dot + 1);
						$file = $name . ($ext ? '.' . $ext : '');
						$safelink = $name . '/' . $ext;
						echo '<li><span class="template">'
						. '<a class="title" href="/share/show/' . $safelink . '" title="Просмотр [' . $file . ']">' . capitalize($name) . ' [' . strtoupper($ext) . ']</a>'
						. '<br/><a style="color: #a77;" href="/data/share/' . $file . '">/data/share/' . $file . '</a>'
						. '<span><a href="/share/delete/' . $safelink . '" class="del"></a><span class="fs">' . fs($s[$file]) . '</span></span></span></li>';
					}
					echo '</ul></div>';
				}
				echo '<hr /><a href="/share/upload/">Загрузить файл</a>';
				echo '</div>';
			}
		}

	}

	function fileLoad($file, $dir) {
		$res  = 0;
		if (is_array($file)) {
			$dir = $_SERVER['DOCUMENT_ROOT'] . $dir;
			$tmp  = $file['tmp_name'];
			if (is_uploaded_file($tmp)) {
				$lcl  = $file['name'];
				$size = $file['size'];
				$dot  = mb_strrpos($lcl, '.');
				$ext  = $dot ? substr($lcl, $dot) : '.png';
				$lcl  = $dot ? substr($lcl, 0, $dot) : $lcl;
				$ulcl = mb_convert_encoding($lcl, 'CP-1251', 'UTF-8');
				if ($lcl != $ulcl) $lcl = $ulcl;
				$path = $lcl . $ext;
				if (file_exists($dir . $path))
					@unlink($dir . $path);

//				debug($dir . $path);
				$res  = intval(move_uploaded_file($tmp, $dir . $path)) - 1;
			} else
				$res = -2;
		} else
			$path = $file;

		return array(0 => $res, 1 => $path);
	}

?>