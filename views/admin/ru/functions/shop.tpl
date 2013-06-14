<?php
	require_once 'payments.php';

	$o = msqlDB::o();

	$l = $this->request->getList();
	$action = $l[1];
	switch ($action) {
	case 'reset':
		$o->delete(Payments::TBL_PAYMENTS);
		header('Location: /admin/shop');
		break;
	}

	$cf = &FrontEnd::getInstance()->get('config');
	$s = $o->select(Payments::TBL_SHOP);
	$shop = @mysql_fetch_array($s);
	if ($shop) {
		$service = $shop[Payments::COL_SHOPID];
		$key     = $shop[Payments::COL_SHOPACC];
		$hash    = $shop[Payments::COL_KEYHASH];
	} else {
		$service = '';
		$key     = '';
		$hash    = '';
	}
	$prepayment = $cf->get('prepayment');

	$u = array();
	$p = array('service', 'key', 'hash', 'prepayment');
	foreach ($p as $param) {
		$value = addslashes($_REQUEST[$param]);
		if (preg_match('/^[^\*]+$/i', $value) && (${$param} != $value))
			$u[$param] = $value;
	}

	if (($pre = intval($u['prepayment'])) > 0) {
		$cf->set(array('main', 'prepayment'), $prepayment = $pre);
		$cf->save();
	};
	unset($u['prepayment']);

	if (count($u)) {
		$p = array(
			Payments::COL_SHOPID => $u['service'] ? $u['service'] : $service
		, Payments::COL_SHOPACC => $u['key'] ? $u['key'] : $key
		, Payments::COL_KEYHASH => $u['hash'] ? $u['hash'] : $hash
		);

		$o->debug=1;
		$o->update(Payments::TBL_SHOP, $p, '1');
		$s = $o->select(Payments::TBL_SHOP);
		$shop = @mysql_fetch_array($s);
		if ($shop) {
			$service = $shop[Payments::COL_SHOPID];
			$key     = $shop[Payments::COL_SHOPACC];
			$hash    = $shop[Payments::COL_KEYHASH];
		} else {
			$service = '';
			$key     = '';
			$hash    = '';
		}
		$updated = true;
	}

	$hash = preg_replace('/[^\*]/', '*', $hash);
//	$captcha = preg_replace('/[^\?]/', '?', $captcha);

?>
<br />
<form method="POST" id="upload" class="long" style="height: 190px;" action="/admin/shop">
	<h3>A1Pay &trade; аккаунт</h3>
	<div>
		<label for=ipid>ID сервиса:</label>
		<input type=text id=ipid name=service value="<?=$service?>" />
	</div>
	<div>
		<label for=isecret>Секретный код:</label>
		<input type=text id=isecret name=hash value="<?=$hash?>" />
	</div>
	<div>
		<label for=iaccount>Ключ платежной формы:</label>
		<input type=text id=iaccount name=key value="<?=$key?>" />
	</div>
	<div>
		<label for=iprepay>Процент предоплаты:</label>
		<input type=text id=iprepay name=prepayment value="<?=$prepayment?>" />
	</div>
	<div>
		<label for=isubmit></label>
		<input id=isubmit type=submit value="Сохранить" />
	</div>
</form>
<br/>
<?php

	if ($updated) {
		$this->renderMessage('Изменения сохранены.');
?>
<script>
	new Fx($I('msgbox'), {kind: FX_FADE, from: 500, to: 0, delta: 2500});
</script>
<?php
	}
?>
