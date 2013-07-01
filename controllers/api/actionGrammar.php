<?php
	require_once 'core_page.php';

	class actionGrammar {
		function execute($params) {
			$ga = GrammarAggregator::getInstance();
			$uid = User::get()->ID();
			if (intval($params['delete'])) {
				if (User::ACL() >= ACL::ACL_MODER) {
					$id = intval($params['id']);
					if ($id && $ga->delete($id))
						JSON_Result(JSON_Ok);
					else
						JSON_Result(JSON_Fail, 'deletion failed');
				} else
					JSON_Result(JSON_Fail, 'access forbidden');
				return true;
			}

			$page = intval($params['page']);
			$zone = preg_replace('"[^\w\d:,/\?\&=]"', '', $params['zone']);
			$range = preg_replace('/[^\d:,]/', '', $params['range']);
			$replace = str_replace('\'', '&quot', strip_tags($params['replacement']));
			if (!($page && $zone && $range && $replace))
				JSON_Result(JSON_Fail, "Page, text range or replacement not specified");


			$d = $ga->fetch(array('nocalc' => true, 'desc' => 0,
			'filter' => "`user` = '$uid' and `range` = '$range' and `zone` = '$zone'"));
			if ($d['total'])
				JSON_Result(JSON_Fail, "You already submitted suggestion for that piece of text");

			$id = $ga->add(array('user' => $uid, 'page' => $page, 'zone' => $zone, 'range' => $range, 'replacement' => $replace));

			if ($id)
				JSON_Result(JSON_Ok, $ga->get($id));
			else
				JSON_Result(JSON_Fail, "insertion failed");
			return true;
		}
		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}
	}

	function fetch_field($arr, $field) {
		$f = array();
		foreach ($arr as $row)
			$f[] = $row[$field];

		return $f;
	}