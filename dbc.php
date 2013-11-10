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
</form><br />
Snippets:<br />
<textarea>select count(id) as c, referrer from visitors where referrer not like "%//ankhzet%" group by referrer order by c desc</textarea>
<hr />
<?php debug2($f)?>

<!--LiveInternet counter --><sc ript type="text/javascript"><!--
document.write("<a style='float: right; padding: 0 5px;' href='http://www.liveinternet.ru/click' "+
"target=_blank><img src='//counter.yadro.ru/hit?t28.1;r"+
escape(document.referrer)+((typeof(screen)=="undefined")?"":
";s"+screen.width+"*"+screen.height+"*"+(screen.colorDepth?
screen.colorDepth:screen.pixelDepth))+";u"+escape(document.URL)+
";h"+escape(document.title.substring(0,80))+";"+Math.random()+
"' alt='' title='LiveInternet: показано число посетителей за"+
" сегодня' "+
"border='0'><\/a>")
//--></script><!--/LiveInternet-->
