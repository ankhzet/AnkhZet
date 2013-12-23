<?php

	require_once 'base.php';
	require_once ENGINE_ROOT . '/api.php';

	$action = strtolower(post('action'));

	$uri = $_SERVER['REQUEST_URI'];
	$q = strpos($uri, '?');
	if ($q !== false) {
		$uri = substr($uri, $q + 1);
		if ($uri != '') {
			parse_str($uri, $a);
			$_REQUEST = (array_merge($_REQUEST, $a));
		}
	}
	$acl = User::ACL();
	$moder = $acl >= ACL::ACL_MODER;
	$aacl = API::getACLs();
	$deny = isset($aacl[$action]) ? $aacl[$action] > $acl : !$moder;
	if ($deny) {
		set_include_path($include);
		require_once '404.php';
		exit(PHP_EOL . '<!-- ;) -->');
	}

	if (API::handle($action, $_REQUEST) === false)
		die('METHOD_UNKNOWN');
