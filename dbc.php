<?php
	include 'base.php';

	require_once SUB_DOMEN . '/_engine/frontend.php';
	FrontEnd::getInstance();


	$dbc = msqlDB::o();
	$dbc->debug = 1;
	$s = $dbc->query($_POST['query']);
	$f = $s ? $dbc->fetchrows($s) : array();

?>
<form method="post">
	<textarea name="query" rows=20 cols=40><?=$_POST['query']?></textarea><br />
	<input type=submit />
</form>
<hr />
<?php debug2($f)?>