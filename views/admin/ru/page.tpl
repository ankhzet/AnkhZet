<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>{%title#unhtml%} - {%site#unhtml%}</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<link rel="stylesheet" href="/theme/admin/admin.css" type="text/css" media="screen, projection" />
		<script src="/theme/js/jquery.js"></script>
	</head>
	<body>
		<div id="wrapper">
			<div id="header">
				<div class="f_r"></div>
				<div class="f_c">
					<span class="site">[@<a href="/">{%site#unhtml%}</a>]</span>
					<span style="float: left; width: auto; text-align: center; position: relative; top: 3px;">{%title#unhtml%}</span>
					{%tpl:content#loginform%}
				</div>
			</div>
			<div id="middle">
				<div id="sideLeft">
					{%tpl:sidebar#links%}
				</div>
				<div id="container">
					<div id="content">
{%content%}
					</div>
					<div class="f_rr"></div>
				</div>
			</div>
			<div id="footer">
				<div class="f_r"></div>
				<div class="f_c">
					Copyright &copy; <a href="mailto:ankhzet@gmail.ru">NAIL Projects</a> 2012
				</div>
			</div>
		</div>
	</body>
</html>