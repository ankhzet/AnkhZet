<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{%title#unhtml%} - {%site#unhtml%}</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
{%TPL:content#meta%}
<?php
	if ($uid = User::get()->ID()) {
?>
	<link href="/api.php?action=rss&channel=<?=$uid?>" type="application/rss+xml" rel="alternate" title="RSS Feed">
<?php } ?>
	<link rel="stylesheet" href="/theme/css/style.css" type="text/css" media="screen, projection" />
	<link rel="stylesheet" href="/theme/css/upd.css" type="text/css" media="screen, projection" />
	<script type="text/javascript" src="/theme/js/jquery.js"></script>
	<script type="text/javascript" src="/theme/js/upform.js"></script>
	{%tpl:content#utils%}
</head>
<body>
	<div class="sidebar">
		<div class="sidecont">
{%TPL:sidebar#menu%}

			<div class="menu user">
{%tpl:content#user%}

			</div>
		</div>
	</div>
	<div id="content">
		<div class="content">
{%patt:ustatic%}
		</div>
	</div>

</div>
</body>
</html>