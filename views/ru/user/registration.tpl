<?php
	$rdat = $this->regdata ? $this->regdata : array();
	require_once 'captcha.php';
	$c = new Captcha();
	$rid = $c->generate();

	$errs = $this->regstate ? $this->regstate : array();
	$e = '';
	if (count($errs) > 0) {
		foreach ($errs as $err)
			$e .= '<br /><span class="red">' . Loc::lget('regerr_' . $err) . '</span>';
		$e .= '<div class="head"></div><br/>';
	}

	$this->errors = $errs;
	function error($view, $key) {
		echo ' for="i' . $key . '"' . ($view->errors[$key] ? ' style="color: red;"' : '');
	}
?>
			<form class="register" action="?" method="post">
				<br />
				<input type=hidden name=action value=registration />
				<input type=hidden name=rid value="<?=$rid?>" />
				<input type=hidden name=url value="<?=htmlspecialchars($_REQUEST['url'])?>" />

				<input id=iemail type="text" name="email" value="<?=$rdat['email']?>" /><label<?=error($this, 'email')?>>Электронная почта<span>*</span></label><br /><br />
				<input id=ipass type=password name="pass" value="<?=$rdat['pass']?>" /><label<?=error($this, 'pass')?>>Пароль<span>*</span></label><br /><br />
				<input id=ipass2 type=password name="pass2" value="<?=$rdat['pass2']?>" /><label<?=error($this, 'pass2')?>>Повтор пароля<span>*</span></label><br /><br />

				<input id=iname type="text" name="name" value="<?=$rdat['name']?>" /><label<?=error($this, 'name')?>>Имя <span>*</span></label><br /><br />

				<input id=icaptcha type=text class="captcha" name="captcha" /><label<?=error($this, 'captcha')?>>CAPTCHA<span>*</span></label>
				<br /><br />
				<img class="captcha-img" src="/models/core_captcha.php?rid=<?=$rid?>" alt="" /><label>&nbsp;</label>
				<br /><br />

				<label>&nbsp;</label><input type="submit" value="Зарегистрироваться" />
			</form>
			<?=$e?>
