<?php
	// initialization step

	global $step, $data;
	$data = $_REQUEST[data];

	class AdminInit {
		var $step = 0;
		var $view = null;
		var $hash = array(0 => 'start', 1 => 'dbuserinit', 3 => 'dbinit', 2 => 'inittables', 4 => 'done');

		function __construct(View $view) {
			$this->view = $view;
		}

		public function process() {
			$r = $this->view->request->getList();
			if ($r[0] == 'init') array_shift($r);

			$this->params = $this->view->request->getArgs();
			$this->data = $this->params[data];
			$stepid = intval($this->params[step]);
			$stepid = $stepid ? $stepid : 1;
			$step = $this->hash[$stepid];

			$step = strtolower($step);
			$step[0] = strtoupper($step[0]);
			$action = 'step' . $step;

			$arr = get_class_methods(get_class($this));
			foreach ($arr as $method)
				if ($method == $action) {
					if ($this->$method($r))
						return $stepid + 1;
					break;
				}
			return $stepid;
		}

		function validate($paramlist) {
			foreach ($paramlist as $param) {
				$v = trim($this->data[$param]);
				if (!$v) return $param;
			}
			return null;
		}

		function stepDbuserinit($r) {
			$execute = $r[0];
			$c = FrontEnd::getInstance()->get('config');

			if ($execute) {
				$p = $this->validate(array('dbname', 'dbhost', 'dbuser'));
				if ($p) {
					$this->view->renderMessage('Field "' . $p . '" is invalid!', View::MSG_ERROR);
					return false;
				}

				$c->set('db.dbname', $this->data[dbname]);
				$c->set('db.host', $this->data[dbhost]);
				$c->set('db.login', $this->data[dbuser]);
				$c->set('db.password', $this->data[dbpassword]);
				$c->save();

				return true;
			};
			global $data;
			$data[dbname] = $c->get('db.dbname');
			$data[dbhost] = $c->get('db.host');
			$data[dbuser] = $c->get('db.login');
			$data[dbpassword] = $c->get('db.password');

			return false;
		}

		function stepDbinit($r) {
			$execute = $r[0];
			if ($execute) {
				$p = $this->validate(array('admin', 'password'));
				if ($p) {
					$this->view->renderMessage('Field "' . $p . '" is invalid!', View::MSG_ERROR);
					return false;
				}

				$db = msqlDB::o();

				User::get()->init_table();
				Ses::init_table();
				$db->delete('users', array('login' => $this->data[admin]));
				$db->insert('users', array('login' => $this->data[admin] , 'password' => md5($this->data[password] . User::PASS_SALT), 'acl' => 0x1000));

				return true;
			} else
				return false;
		}

		function stepInittables($r) {
			$execute = $r[0];
			if ($execute) {

				if (trim($_FILES['sqlfile']['name']) == '') {
					$this->view->renderMessage('SQL database init script skipped', View::MSG_INFO);
					return true;
				}
				$load = $this->view->request->fileLoad($_FILES['sqlfile'], '/', '/data/db-init.sql');
				if ($load[0] < 0) {
					$this->view->renderMessage('Upload failed: ' . Loc::lget('err_fileupload' . (-$load[0])), View::MSG_ERROR);
					return true;
				}

				$path = dirname(__FILE__);
				$p = strpos($path, '/views/admin');
				$path = substr($path, 0, $p) . $load[1];
				$c = @file($path);
				if ($c === false) {
					debug($path);
					$this->view->renderMessage('File upload failed o_O', View::MSG_ERROR);
					return false;
				}
				$c = join(PHP_EOL, $c);
				$c = explode('$$$', $c);

				$db = msqlDB::o();
				$e = array();
				foreach ($c as $q) {
					$query = trim($q);
					if ($query == '') continue;
					$s = $db->query($query);
					if (!$s) {
						$e[] = $db->check($s, $_FILES['sqlfile'][name]);
						echo '<br /><br />] SQL QUERY:<br />' . $query;
					}
				}
				@unlink($path);
				return count($e) == 0;
			} else
				return false;
		}
		function stepDone($r) {
			return false;
		}
	}


	$init = new AdminInit($this);
	$step = $init->process();
	$last = 4;
	function stepDiv($idx) {
		global $step;
		echo '<font' . (($idx == $step) ? '' : ' style="display: none;"') . '>' . PHP_EOL;
	}

?>
<div id=config>
<form enctype="multipart/form-data" id=form action="/config/init/execute" method=post>
	<input type=hidden id=step name=step value=<?echo $step?> />
	<?echo stepDiv(1)?>
		<h3><span></span>MySQL-DB settings</h3>
		<div><label>MySQL DB-name:</label><input type=text name="data[dbname]" value="<?echo $data[dbname]?>" /></div>
		<div><label>MySQL DB-host:</label><input type=text name="data[dbhost]" value="<?echo $data[dbhost]?>" /></div>
		<div><label>MySQL DB-user:</label><input type=text name="data[dbuser]" value="<?echo $data[dbuser]?>" /></div>
		<div><label>Password:</label><input type=text name="data[dbpassword]" value="<?echo $data[dbpassword]?>" /></div>
	</font>
	<?echo stepDiv(2)?>
		<h3><span></span>Module-specific MySQL initialization</h3>
		<div><label>Path to initialization file:</label><input type=file name="sqlfile" /></div>
	</font>
	<?echo stepDiv(3)?>
		<h3><span></span>Site admin</h3>
		<div><label>Admin login:</label><input type=text name="data[admin]" value="<?echo $data[admin]?>" /></div>
		<div><label>Admin password:</label><input type=text name="data[password]" value="<?echo $data[password]?>" /></div>
	</font>
<? if ($step != $last) { ?>
	<div><label></label><input type=submit value=" Next step " /></div>
<?}?>
</form>
</div>
	<?echo stepDiv(4)?>
		<?$this->renderMessage('Initialization complete!', View::MSG_INFO);?>
		<center>
		<? $this->renderButton('Done', '/config'); ?>
		</center>
	</font>