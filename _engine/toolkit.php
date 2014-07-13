<?php

	function getutime(){
		list($usec, $sec) = explode(' ', microtime());
		return ((double)$usec + (double)$sec);
	}

	function debug($var, $msg = 'dumping var') {
		require_once 'frontend.php';
		$f = FrontEnd::getInstance();
		$e = $f->getResponse();
		ob_start();
		debug2($var, $msg);
		$cnt = ob_get_contents();
		ob_end_clean();
		$e->append($cnt);
	}

	function debug2($var, $msg = 'dumping var') {
		ob_start();
		var_dump($var);
		$cnt = ob_get_contents();
		$e = ob_get_contents();
		ob_end_clean();
		$cnt = preg_replace(array(
			'/(\n[\40]*\()/'
		, '/=>\n[\40]*/'
		, '/\[\"([^\"]*)\"\]/'
		, '/"/'
		, '/int\(([^\)]*)\)/i'
		, '/\40string\([^\)]*\)/i'
		, '/\{\$/'
		, '/bool\(([^\)]*)\)/i'
		, '/object\(([^\)]+)\)\#([0-9]+)[^\(]+\([^\)]+\)/i'
		, '/(msqldb[^\{]+)[^\}]+\}/i'
		, '/([\n\40]+\[[0-9]+\]=> \"0\")/'
		, '/data[^\{]*\{[\n\40]*\}/i'
		, '/ /'
		),
		array(
			' ('
		, '=> '
		, '$1 '
		, '<=quot=>'
		, '$1'
		, ''
		, '{&#36;'
		, '$1'
		, '<u>$1</u> (#$2)'
		, '$1'
		, ''
		, 'data => &lt;nulls&gt;'
		, '&nbsp;'
		), $cnt);
		$cnt = preg_replace_callback('/<=quot=>(.*?)<=quot=>[\r\n]+/mDs', 'debug_pr', $cnt);
		$cnt = str_replace(array(htmlspecialchars('<=quot=>'), "\n"), array('&quot;', '<br />'), $cnt);
		echo '<b>' . $msg . '</b>:<BR><br /><span style="white-space: pre-line; line-height: 12px; font-family: Consolas; font-size: 10px">' . $cnt . '</span><br>';
	}

	function debug_pr($v1) {
		return '"' . htmlspecialchars(str_replace('&nbsp;', ' ', $v1[1])) . '"' . PHP_EOL;
	}

