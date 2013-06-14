<?php
	require_once 'link.php';
	require_once 'localization.php';
	$l = $this->request->getList();
	if (intval($l[1]) == $l[1]) {
		unset($l[0]);
		unset($l[1]);
		$l = '/' . join('/', $l);
	} else {
		$l[0] = 'admin';
		$l = join('/', $l);
	}

	$a = $this->request->getArgs();
	$r = ($r = $a['return']) ? $r : '';
	unset($a['return']);

	function makeArgs($args) {
		$r = array();
		foreach ($args as $key => $arg)
			if (is_array($arg))
				foreach ($arg as $p => $val)
					if ($val)
						$r[] = $key . '[' . $p . ']=' . $val;
					else;
			else
				if ($arg)
					$r[] = $key . '=' . $arg;
		return join('&', $r);
	}


	if (count($a))
		$l .= '?' . makeArgs($a);

	$this->renderMessage(Loc::lget('msg_confirm') . ' ' . $l, View::MSG_ERROR);
	echo '<center>';
	$this->renderButton(Loc::lget('word_yes'), $l);
	echo ' ';
	$this->renderButton('<b>' . Loc::lget('word_no') . '</b>', '/' . $r);
	echo '</center>';
?>
