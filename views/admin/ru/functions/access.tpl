<?php

	global $step, $data;
	$data = $_REQUEST[data];

	class AdminAccess {
		var $step = 0;
		var $view = null;
		var $hash = array(1 => 'start', 2 => 'check', 3 => 'fix', 4 => 'done');

		var $acc_DIR = array(
			'/data/share' => 0755
		, '/data/thumbnails' => 0755
		, '/views/ru/cache' => 0755
		, '/views/ru/static' => 0755
		, '/views/admin/ru/cache' => 0755
		);
		var $acc_FILE = array(
			'/_engine/config.ini' => 0755
		, '/locale.ini' => 0755
		, '/media1.ini' => 0755
		, '/media2.ini' => 0755
		, '/contacts.ini' => 0755

		);
		var $chdir = array();
		var $chfile = array();

		function __construct(View $view) {
			$this->view = $view;
		}

		public function process() {
			$r = $this->view->request->getList();
			if ($r[0] == 'access') array_shift($r);

			$this->params = $this->view->request->getArgs();
			$this->data = $this->params[data];
			$stepid = intval($this->params[step]);
			$stepid = $stepid ? $stepid : 1;
			$step = $this->hash[$stepid];

			$step = ucfirst(strtolower($step));
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

		function stepStart($r) {
			$ad = array();
			foreach ($this->acc_DIR as $dir => $chmod) {
				$fp = @fileperms(SUB_DOMEN . $dir) & 0777;
				$ad[$dir] = array(0 => $fp == $chmod, 1 => $fp, 2 => $chmod);
			}

			$c = array(false => '<b class="dis">Fail</b>', true => '<b class="all">Ok</b>');
			foreach ($ad as $dir => $ok)
				$this->chdir[] = '<div><label>' . $dir . ':</label>' . $c[$ok[0]] . ' (' . sprintf('%o', $ok[1]) . ' => ' . sprintf('%o', $ok[2]) . ')</div>';

			$ad = array();
			foreach ($this->acc_FILE as $dir => $chmod) {
				$fp = @fileperms(SUB_DOMEN . $dir) & 0777;
				$ad[$dir] = array(0 => $fp == $chmod, 1 => $fp, 2 => $chmod);
			}

			foreach ($ad as $dir => $ok)
				$this->chfile[] = '<div><label>' . $dir . ':</label>' . $c[$ok[0]] . ' (' . sprintf('%o', $ok[1]) . ' => ' . sprintf('%o', $ok[2]) . ')</div>';

			return $r[0] == 'execute';
		}

		function stepCheck($r) {
			$ad = array();
			foreach ($this->acc_DIR as $dir => $chmod) {
				$fp = @fileperms(SUB_DOMEN . $dir) & 0777;
				if ($fp && ($fp != $chmod))
					$ad[$dir] = (@chmod(SUB_DOMEN . $dir, $chmod) === true);
				else
					$ad[$dir] = false;
			}

			$c = array(false => '<b class="dis">Fail</b>', true => '<b class="all">Ok</b>');
			foreach ($ad as $dir => $ok)
				$this->chdir[] = '<div><label>' . $dir . ':</label>' . $c[$ok] . '</div>';

			$ad = array();
			foreach ($this->acc_FILE as $dir => $chmod) {
				$fp = @fileperms(SUB_DOMEN . $dir) & 0777;
				if ($fp && ($fp != $chmod))
					$ad[$dir] = (@chmod(SUB_DOMEN . $dir, $chmod) === true);
				else
					$ad[$dir] = false;
			}

			foreach ($ad as $dir => $ok)
				$this->chfile[] = '<div><label>' . $dir . ':</label>' . $c[$ok] . '</div>';

			return $r[0] == 'execute';
		}

		function stepFix($r) {
			return true;
		}

		function stepDone($r) {
			return false;
		}
	}


	$unit = new AdminAccess($this);
	$step = $unit->process();
	$steps = array_keys($unit->hash);
	$last = $steps[count($steps) - 1];
	function stepDiv($idx) {
		global $step;
		echo '<font' . (($idx == $step) ? '' : ' style="display: none;"') . '>' . PHP_EOL;
	}

?>
<div id=config>
<form enctype="multipart/form-data" id=form action="/admin/access/execute" method=post>
	<input type=hidden id=step name=step value=<?echo $step?> />
	<?=stepDiv(1)?>
		<h3><span></span>Этот скрипт поможет проверить необходимый уровень доступа к файловой системе</h3>
		<div><label></label><input type=text disabled style="background: transparent; border: 0; width: 100%;" value="Нажмите &quot;Next&quot; для продолжения" /></div>
	</font>
	<?=stepDiv(2)?>
		<h3><span></span>Директории (к которым требуется особый уровень доступа)</h3>
		<?=join(PHP_EOL, $unit->chdir)?>

		<h3><span></span>Файлы (к которым требуется особый уровень доступа)</h3>
		<?=join(PHP_EOL, $unit->chfile)?>

	</font>
	<?=stepDiv(3)?>
		<h3><span></span>Исправляем уровни доступа...</h3>
		<h3><span></span>Директории</h3>
		<?=join(PHP_EOL, $unit->chdir)?>

		<h3><span></span>Файлы</h3>
		<?=join(PHP_EOL, $unit->chfile)?>

		<div><label></label><input type=text disabled style="background: transparent; border: 0; width: 100%;" value="Если изменить уровни не удалось, это следует сделать вручную" /></div>
	</font>
<? if ($step != $last) { ?>
	<div><label></label><input type=submit value=" Next step " /></div>
<?}?>
</form>
</div>
	<?=stepDiv(4)?>
		<?$this->renderMessage('Work complete!', View::MSG_INFO);?>
		<center>
		<? $this->renderButton('Done', '/admin'); ?>
		</center>
	</font>