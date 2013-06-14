<?php

	require_once dirname(__FILE__) . '/dbengine.php';
	require_once dirname(__FILE__) . '/user.php';

	define(SP_TID    , 'tid');
	define(SP_KEY    , 'key');
	define(SP_NAME   , 'name');
	define(SP_COMMENT, 'comment');
	define(SP_PID    , 'partner_id');
	define(SP_SID    , 'service_id');
	define(SP_ORDER  , 'order_id');
	define(SP_TYPE   , 'type');
	define(SP_PINCOME, 'partner_income');
	define(SP_SINCOME, 'system_income');
	define(SP_HASH   , 'check');

	class Payments {
		const TBL_SHOP     = '_shop';
		const TBL_PAYMENTS = '_payments';
//		const TBL_INCOMING = '_incoming';
//		const TBL_TRANSACT = '_transactions';
//		const TBL_BALANCE  = '_balance';
//		const TBL_FAILED   = '_failedpayment';

		const COL_SHOPID   = 'serviceid';
		const COL_SHOPACC  = 'key';
		const COL_KEYHASH  = 'secret';

		const PMT_NEW      = 0;
		const PMT_CONFIRMED= 1;
		const PMT_FAILED   = 2;

		static $inst = null;
		static $shop = null;
		var $dbc = null;

		private function __construct() {
			$this->dbc = msqlDB::o();
		}

		static function get() {
			if (!isset(self::$inst))
				self::$inst = new self();
			return self::$inst;
		}

		function getShopData() {
			if (!isset(self::$shop)) {
				$s = $this->dbc->select(self::TBL_SHOP, ' 1 limit 1');
				self::$shop = $s ? @mysql_fetch_assoc($s) : array();
			}
			return self::$shop;
		}

		function getKeyhash() {
			$shopdata = $this->getShopData();
			return $shopdata[self::COL_KEYHASH];
		}

		function genPaymentForm($order, $test = 0) {
			$s = $this->dbc->insert(self::TBL_PAYMENTS, array(
				'orderid' => $order['id']
			, 'time'  => time()
			, 'state' => self::PMT_NEW
			), true);
			$pid = intval(@mysql_result($s, 0));
			if (!$pid) return false;
			$shop = $this->getShopData();
			$d = array(
				'pid' => $pid
			, 'test' => $test ? '?test=1' : ''
			, 'key' => $shop[self::COL_SHOPACC]
			, 'name' => $order['title']
			, 'cost' => $order['tprice']
			, 'phone' => $order['phone']
			, 'email' => $order['mail']
			);
			return $d;
		}

		function checkConfirmation($arr, $hash) {
			$key = $this->getKeyhash();
			$join = join('', $arr) . $key;
			$md5 = md5($join);
			return $md5 == $hash;
		}

		function registerPayment($indata, $safe) {
			$pid    = intval($indata[SP_ORDER]);
			$s = $this->dbc->select(self::TBL_PAYMENTS, '`id` = ' . $pid . ' limit 1', '`id`, `orderid`, `state`');
			if ($s && ($r = @mysql_fetch_assoc($s))) {
//				debug2($r);
//				if (intval($r['state']) <> self::PMT_NEW) return false;
				$orderid = intval($r['orderid']);
//				if (intval($r['orderid']) != $orderid) $safe = false;
			} else
				$s = $this->dbc->insert(self::TBL_PAYMENTS, array('orderid' => $orderid, 'id' => $pid, 'time' => time(), 'state' => self::PMT_NEW));

			require_once dirname(dirname(__FILE__)) . '/models/core_order.php';
			$a = OrderAggregator::getInstance();

//			echo "[pid: $pid]<br>";
//			echo "[safe: $safe]<br>";

			$order = $a->get($orderid);
			$price = intval($order['tprice']);
			$pmt = intval($order['payment']);
			$updatestate = OST_PAYED;
//			debug2($order, $orderid);
			switch ($payment = intval($order['payment'])) {
			case PMT_PREORDER:
				$con = $a->getContact($orderid, 0);
				$payall = intval($con['data']);
				if ($payall != 1) { // prepay less than 100%
					require_once 'config.php';
					$config = Config::read('INI', '_engine/config.ini');

					$pre = (float)$config->get('prepayment');
					switch (intval($order['state'])) {
					case OST_FORMED:
						$price = round($price * ($pre / 100.0));
//						$updatestate = OST_PREPAYED;
						break;
//					case OST_PREPAYED:
//						$price = $price - round($price * ($pre / 100.0));
//						break;
					}
				} else;

				break;
			default:
			}
//			debug2($price, $pre);
			$amount = (float)$indata[SP_SINCOME];

			$shop = $this->getShopData();
			if (($shop[self::COL_SHOPID] != $indata[SP_SID]) || ($amount < $price))
				$safe = false;
//			echo "[safe: $safe]<br>";

			$arr = array('amount' => $amount, 'transaction' => $indata[SP_TID], 'state' => $safe ? self::PMT_CONFIRMED : self::PMT_FAILED);

			$this->dbc->update(self::TBL_PAYMENTS, $arr, '`id` = \'' . $pid . '\' and `orderid` = ' . $order['id']);

			if ($safe) {
				$order['served'] = time();
				$order['state'] = $updatestate;
				$a->update($order, $orderid);
			}

			return $safe;
		}
	}
?>
