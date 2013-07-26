<?php
	require_once 'core_visitors.php';
	require_once 'core_uasparser.php';
	require_once 'AggregatorController.php';

	class VisitorsController extends AggregatorController {
		protected $_name  = 'visitors';

		var $MODER_EDIT   = '<span class="pull_right">[<a href="/{%root}/delete/{%id}?page={%page}">{%delete}</a>]</span>';
		var $ADD_MODER    = 0;
		var $EDIT_STRINGS = array();
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array();
		var $link         = '';
		var $ID_PATTERN   = '
				<div>{%time}</div>
';
		var $LIST_ITEM  = '
					<div class="cnt-item ua-{%type}">
						<div class="title">
							<span class="head" style="display: block; float: left; overflow: hidden; width: 35%;">
								<img style="margin: 3px 0 -3px" width=16 height=16 src="/theme/img/ua/{%ua_ico}" title="{%ua}" alt="{%ua}" />
								<img style="margin: 3px 0 -3px" width=16 height=16 src="/theme/img/os/{%os_ico}" title="{%os}" alt="{%os}" />
								{%user:name}&nbsp; <a href="{%uri}">{%uri}</a>{%moder}
							</span>
							<span class="link size">{%time}</span>
							<span class="link size">{%date}</span>
							<span class="link" style="width: 50%; font-size: 50%; color: #888; overflow: hidden;">{%ua_string}</span>
						</div>

					</div>
';

		function getAggregator() {
			return VisitorsAggregator::getInstance();
		}

		public function actionPage($r) {
			$uas = new UASParser();
			$uas->SetCacheDir(ROOT . "/cache/");
			$this->uas = $uas->_loadData();

			$t = 60 * 60 * 24;
			$va = $this->getAggregator();

			$f = array('1');
			$q = array();

			if ($ip = post('ip')) {
				$f[] = "inet_ntoa(`ip`) like '%$ip%'";
				$q[] = "ip=$ip";
			}
			if ($days = time() - $t * post('days')) {
				$f[] = "`time` >= $days";
				$q[] = "days=" . post('days');
			}
			switch ($bots = uri_frag($_REQUEST, 'bots', 1)) {
			case 0: break;
			case 1:
				$f[] = "`type` >= 0";
				$q[] = "bots=$bots";
				break;
			case 2:
				$f[] = "`type` < 0";
				$q[] = "bots=$bots";
				break;
			}

			$_REQUEST['bots'] = $bots;

			$this->query = join(' and ', $f);
			$this->link = '?' . join('&', $q);

//			$va->dbc->debug = 1;
			$s = $va->dbc->select($va->TBL_FETCH, join(' and ', $f) . ' group by `ip` order by `count`', 'count(`ip`) as `count`, `ip`');
			$e = array();
			$c = 0;
			foreach ($va->dbc->fetchrows($s) as $row) {
				$c += intval($row['count']);
				$row['ip'] = long2ip(intval($row['ip']));
				$e[] = patternize('{%ip}: {%count} requests', $row);
			}
			$e = join(', ', $e);
			$e = "<br /><span style=\"font-weight: normal; font-size: 80%; color: #888\">$e<br />$c page requests total</span>";

			View::addKey('hint', $e);
			parent::actionPage($r);
		}

		public function makeItem(&$aggregator, &$row) {
			$uas = &$this->uas;
			$bot = $row['type'] < 0;
			$row['ua_ico'] = 'unknown.png';
			switch ($bot) {
			case true:
				$row['type'] = 'bot';
				$a = uri_frag($uas['robots'], $row['ua'], 0, 0);
				if (uri_frag($a, 1, 0, 0)) {
					$row['ua'] = $a[1];
					$row['ua_ico'] = $a[6];
				}
				break;
			case false:
				$row['type'] = str_replace(' ', '-', strtolower($uas['browser_type'][$row['type']][0]));
				$a = uri_frag($uas['browser'], $row['ua'], 0, 0);
				if (uri_frag($a, 1, 0, 0)) {
					$row['ua'] = $a[1];
					$row['ua_ico'] = $a[5];
				}
				break;
			}

			$os = uri_frag($uas['os'], $row['os'], 0, 0);
			if (uri_frag($os, 1, 0, 0)) {
				$row['os'] = $os[1];
				$row['os_ico'] = $os[5];
			} else
				$row['os_ico'] = 'unknown.png';

			$row['date'] = $row['time'];
			$row['time'] = date('H:i:s', $row['utime']);
			$row['user:name'] = ($u = intval($row['user'])) ? '<a href="/user/' . $u . '">' . User::get($u)->readable() . '</a>' : '&lt;guest&gt;';
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			return patternize($this->ID_PATTERN, $row);
		}

	}
?>