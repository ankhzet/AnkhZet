<?php
	require_once 'table.php';
	require_once 'payments.php';

	class PMR extends DBTableRenderer {
		const T_X        = '';
		const T_ID       = 'id';
		const T_ORDER    = 'orderid';
		const T_USER     = 'tuser';
		const T_AMOUNT   = 'amount';
		const T_TRANSACT = 'transaction';
		const T_TIME     = 'time';
		const T_STATE    = 'state';
		var $user;

		function __construct($p, $link, $filter) {
			if (!$p[order]) {
				$p[order] = 'id';
				$p[dir] = 'desc';
			}
			if (!$p[pagesize]) {
				$p[pagesize] = 10;
			}
			$this->params = $p;
			$this->status = explode(',', Loc::lget('pmtstate'));
			$this->colors = array('gray', 'green; font-weight: bold', 'red; font-weight: bold');
			parent::__construct(
				$link
			, Payments::TBL_PAYMENTS . ' as t left join `order` as o on o.id = t.orderid left join users as u on u.id = o.user'
			, $filter ? $filter : '1'
			, 't.*, o.id as `orderid`, u.login as `tuser`'
			);
		}

		function columns() {
			return array(self::T_X, self::T_ID, self::T_USER, self::T_ORDER, self::T_AMOUNT, self::T_TRANSACT, self::T_TIME, self::T_STATE);
		}

		function colWidths() {
			return array(self::T_X => '24px', self::T_ID => '40px', self::T_USER => 20, self::T_ORDER => '60px');
		}

		function colTitles() {
			return array(
				self::T_ID      => 'ID'
			, self::T_TRANSACT=> 'Transaction ID'
			, self::T_USER    => 'User'
			, self::T_ORDER   => 'Order ID'
			, self::T_AMOUNT  => 'Amount'
			, self::T_TIME    => 'Date'
			, self::T_STATE   => 'State'
			);
		}

		function prepareRow($column, $data) {
			switch ($column) {
			case self::T_X:
				return '<a class="del" href="/admin/confirm/0/admin/pmfilter/delete/' . $data[self::T_ID] . '"></a>';
			case self::T_TIME:
				return date('d/m/Y <b>H:i:s</b>', intval($data[$column]));
			case self::T_AMOUNT:
				return (float) $data[$column] . ' руб.';
			case self::T_USER:
				$u = $data[$column];
				if (!$u)
					return '<i style="color: gray;">unspecified or deleted</i>';
				else
					return $u;
			case self::T_STATE:
				$s = $data[$column];
				return '<span style="font-size: 60%; color: ' . $this->colors[$s] . ';">' . $this->status[$s] . '</span>';
			default:
				return $data[$column];
			}
		}
	}

	$p = $this->request->getArgs();
	$a = array('id', 'user', 'orderid', 'transaction', 'amountmin', 'amountmax', 'datefrom', 'dateto', 'state', 'joinormode');

	foreach ($a as $arg)
		${$arg} = $p[$arg];


	function filterStr(&$list, $param, $value) {
		$list[] = '(`' . $param . '` like \'%' . addslashes($value) . '%\')';
	}

	function filterInt(&$list, $param, $value) {
		$list[] = '(`' . $param . '` = \'' . intval($value) . '\')';
	}

	function filterRange(&$list, $param, $min, $max) {
		if ($max)
			$list[] = '((`' . $param . '` >= \'' . (float)$min . '\') and (`' . $param . '` <= \'' . (float)$max . '\'))';
		else
			$list[] = '(`' . $param . '` >= \'' . (float)$min . '\')';
	}

	function filterCheck(&$list, $param, $value) {
		$list[] = '(`' . $param . '` = \'' . intval(!!$value) . '\')';
	}

	$o = msqlDB::o();

	$l = array();
	if ($id) filterInt(&$l, 't`.`id', $id);
	if ($transaction) filterInt(&$l, 'transaction', $transaction);
	if ($user) filterStr(&$l, 'u`.`login', $user);
	if ($order) filterInt(&$l, 'orderid', $order);
	if ($amountmin || $amountmax) filterRange(&$l, 'amount', $amountmin, $amountmax);
	if ($datefrom || $dateto) filterRange(&$l, 't`.`time', strtotime($datefrom), strtotime($dateto));
	if (isset($state)) filterInt(&$l, 't`.`state', intval($state));

	$jm = array(false => ' AND ', true => ' OR ');
	$filter = join($jm[!!$joinormode], $l);
	$query = str_replace(array('OR (', 'AND ('), array('OR<br> &nbsp; (', 'AND<br> &nbsp; ('), $filter);
	$query = preg_replace(
		array(
			'/(AND|OR)/i'
		, '/(\`([a-z\_0-9]+)\`)/i'
		, '/(\'(\%?)([^\'\%]+)(%?)\')/i'
		)
	, array(
			'<b>$1</b>'
		, '`<span style="color: green">$2</span>`'
		, '\'$2<span style="color: blue"><b>$3</b></span>$4\''
		)
	, $query
	);
	if ($query) echo '<br />Фильтр: [<br /> &nbsp; ' . $query . '<br />]<br /><hr />';

	$a = array();
	foreach ($p as $arg => $value)
		$a[] = $arg . '=' . $value;
	$tr = new PMR($p, '/admin/pmfilter/?', $filter);
	$tr->render();
	echo '<hr />';

?>
<br>
<form method="POST" id="upload" class="long" style="height: 440px;" action="/admin/pmfilter">
	<h3>Поиск платежа</h3>
	<div>
		<label for=iid>Номер платежа:</label>
		<input type=text id=iid name=id value="<?=$id?>" />
	</div>
	<div>
		<label for=ipm>ID заказа:</label>
		<input type=text id=ipm name=orderid value="<?=$order?>" />
	</div>
	<div>
		<label for=iuser>Пользователь:</label>
		<input type=text id=iuser name=user value="<?=$user?>" />
	</div>
	<div>
		<label for=iammount>Сумма:</label>
		<input style="width: 90px;" type=text id=iammount name="amountmin" value="<?=$amountmin?>" />-<input style="width: 90px;" type=text name="amountmax" value="<?=$amountmax?>" />
	</div>
	<div>
		<label for=itransaction>Номер транзакции:</label>
		<input type=text id=itransaction name=transaction value="<?=$transaction?>" />
	</div>
	<div>
		<label for=idate>Дата:</label>
		<input style="width: 90px;" type=date id=idate name="datefrom" value="<?=$datefrom?>" />-<input style="width: 90px;" type=date name="dateto" value="<?=$dateto?>" />
	</div>
	<div>
		<label>Состояние:</label>
	</div>
<?php
	$a = array(0, 1, 2);
	foreach ($a as $s)
		echo
				'<div>' . ($s ? '<label></label>' : '') . PHP_EOL . '<input style="width: 20px; padding: 4px;" type=radio id=istate' . $s . ' name=state value="' . $s . '" '
			. (isset($state) && ($s == $state) ? ' checked' : '') . ' />'
			. '<label style="clear: none; float: none; display: inline; margin: 0px;" for=istate' . $s . '>' . $tr->status[$s] . '</label></div>' . PHP_EOL;
?>
	<div>
		<label for=ijoin>Любое совпадение:</label>
		<input style="width: 20px; padding: 4px;" type=checkbox id=ijoin name=joinormode value="1" <?echo $joinormode ? ' checked' : ''?> />
	</div>
	<div>
		<label for=isubmit></label>
		<input id=isubmit type=submit value="Фильтр" />
	</div>
</form>
<br/>
