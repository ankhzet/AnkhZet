<?php
	require_once 'core_visitors.php';
	require_once 'core_uasparser.php';
	require_once 'AggregatorController.php';

	class VisitorsController extends AggregatorController {
		protected $_name = 'visitors';

		var $EDIT_STRINGS = array();
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array();
		var $link = '';
		var $ID_PATTERN = '
				<div>{%time}</div>
';
		var $LIST_ITEM  = '
					<div>
						{%type}, {%ua}, {%os}, {%uri}, {%cast} sec, <span class="time">{%time}</span><br />
						<span style="font-size: 80%; color: #888;">{%ua_string}</span>
					</div>
';

		function getAggregator() {
			return VisitorsAggregator::getInstance();
		}

		public function action() {
			$uas = new UASParser();
			$uas->SetCacheDir(ROOT . "/cache/");
			$this->uas = $uas->_loadData();
			parent::action();
		}

		public function makeItem(&$aggregator, &$row) {
			$uas = &$this->uas;
			$row['type'] = $row['type'] < 0 ? 'Robot' : $uas['browser_type'][$row['type']][0];
			$row['ua'] = $row['type'] < 0 ? $uas['robots'][$row['ua']][1] : $uas['browser'][$row['ua']][1];
			$row['os'] = $uas['os'][$row['os']][1];
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			return patternize($this->ID_PATTERN, $row);
		}

	}
?>